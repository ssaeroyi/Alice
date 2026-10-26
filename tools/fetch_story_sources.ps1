<#
.SYNOPSIS
    Gutenberg story-source downloader (proven ladder). See tools/FETCHING.md.

.DESCRIPTION
    Downloads raw PG texts using the URL fallback chain proven across batches
    3-5, with every throttle countermeasure baked in. Workflow v3 (2026-08-23):
    assistant fetches everything itself - this script IS the default path.

    LADDER per book id:
      1. https://www.gutenberg.org/cache/epub/<id>/pg<id>.txt
      2. https://www.gutenberg.org/ebooks/<id>.txt.utf-8
      3. https://aleph.gutenberg.org/<d>/<i>/<digits>/pg<id>.txt  (digit-split)

    COUNTERMEASURES (do not remove):
    - TLS12 forced (PS 5.1 default too old).
    - HttpWebRequest instead of Invoke-WebRequest: IWR .Content nulls out on
      some raw/text endpoints under PS 5.1.
    - >=20KB size floor + delete-on-fail so a throttled HTML error page never
      masquerades as a downloaded book.
    - EXISTS-SKIP idempotency; safe to rerun after a throttle window passes.
    - 45s spacing BETWEEN BOOKS: PG throttles by IP with long windows, and a
      burst reliably triggers 503/504 after ~2 quick successes.
    - DO NOT GUESS OTHER MIRRORS: aleph/pglaf/mirrorservice/IA digit-split
      layouts were all tried in batch 3; most 404/503, pglaf layout changed,
      aleph had broken SSL for PS 5.1. The ladder above is what worked.

.PARAMETER VerifyTitles
    Before downloading, confirm each PG id via gutendex.com/books/<id>
    (title + first author printed for eyeball check, 1.5s pacing).

.EXAMPLE
    .\fetch_story_sources.ps1                          # download the table below
    .\fetch_story_sources.ps1 -VerifyTitles            # gutendex pre-flight first
#>
param([switch]$VerifyTitles)

$ErrorActionPreference = 'Continue'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$destDir = 'C:\Users\Martin\AppData\Local\Temp\opencode'
$ua = 'AliceLibraryCuration/1.0 (educational project)'

# Edit this table per batch: PG id -> output filename.
# Batch 8 (2026-08-23): micro-shorts unit; Baldwin/O.Henry/Fabre books get sliced per story after download.
# Ids verified via gutendex 2026-08-23: 18442 Fifty Famous Stories Retold / 6168 Fifty Famous People /
#   3707 The Trimmed Lamp / 1583 Options. Fabre book id still unverified (hosts throttling) - add when found.
$books = @(
    @{ id = 18442; name = 'baldwin-fifty' },
    @{ id = 6168;  name = 'baldwin-people' },
    @{ id = 3707;  name = 'ohenry-trimmed-lamp' },
    @{ id = 1583;  name = 'ohenry-options' }
)

if ($VerifyTitles) {
    foreach ($b in $books) {
        try {
            $json = Invoke-RestMethod -Uri "https://gutendex.com/books/$($b.id)" -TimeoutSec 30 -Headers @{ 'User-Agent' = $ua }
            $author = ($json.authors | Select-Object -First 1).name
            Write-Output ("VERIFY #{0,-5} '{1}' by {2}" -f $b.id, $json.title, $author)
        }
        catch {
            Write-Output ("VERIFY #{0,-5} FAILED - {1}" -f $b.id, $_.Exception.Message)
        }
        Start-Sleep -Milliseconds 1500
    }
}

function Fetch-Url {
    param([string]$url, [string]$out)
    try {
        $req = [System.Net.HttpWebRequest]::Create($url)
        $req.UserAgent = $ua
        $req.Timeout = 40000
        $req.ReadWriteTimeout = 40000
        $resp = $req.GetResponse()
        $stream = $resp.GetResponseStream()
        $fs = [IO.File]::Create($out)
        $stream.CopyTo($fs)
        $fs.Close(); $stream.Close(); $resp.Close()
        return ((Get-Item $out).Length -gt 20000)
    } catch { return $false }
}

function Split-Digits {
    param([int]$id)
    return (($id.ToString().ToCharArray()) -join '/')
}

foreach ($b in $books) {
    $out = Join-Path $destDir ($b.name + '.txt')
    if (Test-Path $out) { Write-Output ('SKIP #' + $b.id + ' (' + $b.name + ') already present'); continue }

    $ds = Split-Digits $b.id
    $urls = @(
        ('https://www.gutenberg.org/cache/epub/' + $b.id + '/pg' + $b.id + '.txt'),
        ('https://www.gutenberg.org/ebooks/' + $b.id + '.txt.utf-8'),
        ('https://aleph.gutenberg.org/' + $ds + '/' + $b.id + '/pg' + $b.id + '.txt')
    )

    $ok = $false
    foreach ($u in $urls) {
        if ($ok) { break }
        if (Fetch-Url $u $out) {
            $kb = [math]::Round((Get-Item $out).Length / 1KB)
            Write-Output ('OK   #' + $b.id + ' -> ' + $b.name + '.txt (' + $kb + ' KB) via ' + ([Uri]$u).Host)
            $ok = $true
        } else {
            Write-Output ('fail #' + $b.id + ': ' + $u)
            if (Test-Path $out) { Remove-Item $out -Force }
        }
    }
    if (-not $ok) { Write-Output ('MISS #' + $b.id + ' - wait out the throttle window, then rerun') }
    Start-Sleep -Seconds 45
}
