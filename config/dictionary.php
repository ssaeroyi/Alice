<?php

/*
 |--------------------------------------------------------------------------
 | Dictionary behavior
 |--------------------------------------------------------------------------
 |
 | `blocklist` — exact lemmas the dictionary refuses to look up, save, or
 | show. Enforced in `App\Support\Dictionary::lookup()` at two points: the
 | normalized search term AND the resolved lemma (so inflections like
 | "fucked" resolving to lemma "fuck" are caught too).
 |
 | Matching is exact-lemma only (no substring), so innocent words that merely
 | contain a blocked string — sussex, intersex, cockpit, sextant — are never
 | caught by accident. It is a strict student-facing list: strong profanity,
 | sexual slang and slurs, clinical anatomy terms, hate figures and symbols,
 | and racial/religious/disability slurs are all excluded outright, which
 | means legitimate homographs like "cock" (rooster) or "spook" (ghost) are
 | blocked too. Matching is per-lemma, so multi-word phrases ("white power")
 | cannot be caught — only their single-word forms are listed. "jew"/"jews"
 | are blocked, but "jewish", "judaism", "menorah", etc. stay usable.
 | Extend freely; normalize() lowercases but does not stem, so include the
 | inflections you want blocked as their own rows.
 |
 | Note: already-saved words are deliberately left untouched.
 */

return [
    'blocklist' => [
        // fuck family
        'fuck', 'fucks', 'fucked', 'fucking', 'fuckin', 'fucker', 'fuckers',
        'fuckhead', 'fuckheads', 'fuckface', 'fuckfaces', 'motherfucker',
        'motherfuckers', 'motherfucking', 'motherfuckin',
        // shit family
        'shit', 'shits', 'shitted', 'shitting', 'shitty', 'shite', 'shithead',
        'shitheads', 'bullshit', 'bullshits', 'bullshitting', 'bullshitter',
        // bitch family
        'bitch', 'bitches', 'bitchy', 'bitching', 'bitchin', 'sonofabitch',
        'sonsofbitches',
        // cunt family
        'cunt', 'cunts', 'cunty',
        // dick family
        'dick', 'dicks', 'dickhead', 'dickheads', 'dickface',
        // cock family
        'cock', 'cocks', 'cockhead', 'cockheads',
        // pussy family
        'pussy', 'pussies',
        // ass / asshole family
        'ass', 'asses', 'asshole', 'assholes', 'asshat', 'jackass', 'butthole',
        // vulgar + euphemisms
        'whore', 'whores', 'whoring', 'whored', 'slut', 'sluts', 'slutty',
        'bastard', 'bastards', 'prick', 'pricks', 'douche', 'douches',
        'douchebag', 'douchebags', 'tits', 'titties', 'boobs', 'boobies',
        'boner', 'blowjob', 'blowjobs', 'handjob', 'handjobs', 'cunnilingus',
        'fellatio', 'orgasm', 'orgasms', 'ejaculate', 'ejaculated',
        'masturbate', 'masturbated', 'masturbation', 'wank', 'wanked',
        'wanking', 'porn', 'pornography', 'pornographic',
        // slurs
        'nigger', 'niggers', 'nigga', 'niggas', 'niggaz', 'faggot', 'faggots',
        'fag', 'fags', 'dyke', 'dykes', 'retard', 'retards', 'retarded',
        // sexual assault / acts
        'rape', 'rapes', 'raped', 'raping', 'rapist', 'rapists', 'incest',
        'bestiality', 'pedophile', 'pedophiles',
        // sex
        'sex', 'sexy', 'sexual', 'sexually', 'intercourse', 'fornicate',
        'fornication', 'penetration', 'erection', 'erections',
        // clinical anatomy
        'penis', 'penises', 'vagina', 'vaginas', 'vaginal', 'vulva', 'vulvas',
        'clitoris', 'clitorises', 'testicle', 'testicles', 'testicular',
        'scrotum', 'scrotums', 'anus', 'anuses', 'anal', 'labia', 'labias',
        'breast', 'breasts', 'nipple', 'nipples', 'areola', 'areolas',
        'urethra', 'urethras', 'perineum', 'uterus', 'prostate',
        // hate figures and symbols
        'hitler', 'adolf', 'nazi', 'nazis', 'nazism', 'naziism', 'swastika',
        'swastikas', 'fuhrer', 'fuehrer', 'heil', 'kkk', 'klan', 'klansman',
        'klansmen', 'supremacist', 'supremacists', 'supremacism',
        // antisemitic slurs (jew/jews per user ruling - jewish/judaism stay)
        'jew', 'jews',
        // ethnic and religious slurs
        'kike', 'kikes', 'spic', 'spics', 'spick', 'spicks', 'wetback',
        'wetbacks', 'beaner', 'beaners', 'chink', 'chinks', 'gook', 'gooks',
        'nip', 'nips', 'jap', 'japs', 'paki', 'pakis', 'coon', 'coons',
        'jigaboo', 'jigaboos', 'redskin', 'redskins', 'towelhead',
        'towelheads', 'raghead', 'ragheads', 'sandnigger', 'sandniggers',
        'gypsy', 'gypsies', 'negro', 'negroes', 'negroid', 'mulatto',
        'mulattoes', 'halfcaste', 'halfcastes', 'spook', 'spooks',
        // lgbtq+ slurs
        'queer', 'queers', 'tranny', 'trannies', 'homo', 'homos', 'lesbo',
        'lesbos', 'sodomite', 'sodomites', 'bugger', 'buggers',
        // disability slurs
        'midget', 'midgets', 'cripple', 'cripples', 'gimp', 'gimps', 'spaz',
        'spazz', 'spazzes', 'cretin', 'cretins', 'imbecile', 'imbeciles',
        'moron', 'morons',
        // adult / sex-work terms
        'pimp', 'pimps', 'prostitute', 'prostitutes', 'prostituted',
        'prostituting', 'hooker', 'hookers', 'gigolo', 'gigolos',
        // drug terms
        'cocaine', 'crack', 'meth', 'methamphetamine', 'methamphetamines',
        'heroin', 'ecstasy', 'mdma', 'marijuana', 'hashish',
    ],
];
