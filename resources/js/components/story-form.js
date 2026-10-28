import { updateInsightsSummary } from './story-insights.js';

export function initStoryForm() {
    initReadabilityAuto();
    initCleanFormatting();
    initCoverToggle();
    initCoverDropzone();
    initCoverSearch();
    initSynopsisFill();
    initContentImport();
    initGrammarCheck();
}

const READABILITY_DEBOUNCE_MS = 600;

function countWords(text) {
    const trimmed = text.trim();

    return trimmed === '' ? 0 : trimmed.split(/\s+/).length;
}

function initReadabilityAuto() {
    document.querySelectorAll('[data-readability-analyze-text]').forEach((textarea) => {
        const key = textarea.dataset.readabilityAnalyzeText;
        const panel = document.querySelector(`[data-readability-panel="${key}"]`);
        const note = document.querySelector(`[data-readability-note="${key}"]`);

        if (!panel || !note) {
            return;
        }

        const minWords = Number(textarea.dataset.readabilityMinWords) || 50;
        let timer = null;
        let controller = null;

        const setNote = (message) => {
            note.textContent = message || '';
            note.classList.toggle('hidden', !message);
        };

        const fillPanel = (data) => {
            document.querySelector(`[data-rl-score="${key}"]`).textContent = data.score;
            document.querySelector(`[data-rl-tier="${key}"]`).textContent = data.tier;
            document.querySelector(`[data-rl-grade="${key}"]`).textContent = data.grade_level;
            document.querySelector(`[data-rl-age="${key}"]`).textContent = data.age_range;
            document.querySelector(`[data-rl-level="${key}"]`).textContent = 'Level ' + data.reading_level;
            document.querySelector(`[data-rl-words="${key}"]`).textContent = data.word_count;
            document.querySelector(`[data-rl-sentences="${key}"]`).textContent = data.sentence_count;
            document.querySelector(`[data-rl-syllables="${key}"]`).textContent = data.syllable_count;
            document.querySelector(`[data-rl-letters="${key}"]`).textContent = data.letter_count;

            const badge = document.querySelector(`[data-rl-level-badge="${key}"]`);
            if (badge) {
                badge.textContent = 'Level ' + data.reading_level;
                badge.classList.remove('hidden');
            }
        };

        const hideBadge = () => {
            const badge = document.querySelector(`[data-rl-level-badge="${key}"]`);
            if (badge) {
                badge.classList.add('hidden');
                badge.textContent = '';
            }
        };

        const analyze = async () => {
            const words = countWords(textarea.value);

            if (words === 0) {
                panel.classList.add('hidden');
                setNote('');
                hideBadge();
                updateInsightsSummary();

                return;
            }

            if (words < minWords) {
                panel.classList.add('hidden');
                setNote('Add ' + (minWords - words) + ' more word' + (minWords - words === 1 ? '' : 's')
                    + ' — the level check needs at least ' + minWords + '.');
                hideBadge();
                updateInsightsSummary();

                return;
            }

            setNote('');

            if (controller) {
                controller.abort();
            }
            controller = new AbortController();

            try {
                const response = await fetch(textarea.dataset.readabilityUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ text: textarea.value }),
                    signal: controller.signal,
                });

                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Level check failed. Keep typing and it will retry.');
                }

                fillPanel(result.data);
                panel.classList.remove('hidden');
                updateInsightsSummary();
            } catch (analysisError) {
                if (analysisError.name === 'AbortError') {
                    return;
                }

                panel.classList.add('hidden');
                setNote(analysisError.message);
                updateInsightsSummary();
            }
        };

        textarea.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(analyze, READABILITY_DEBOUNCE_MS);
        });

        analyze();
    });
}

function initCleanFormatting() {
    document.querySelectorAll('[data-clean-format]').forEach((button) => {
        button.addEventListener('click', () => {
            const textarea = document.querySelector(`[data-readability-analyze-text="${button.dataset.cleanFormat}"]`);

            if (!textarea) {
                return;
            }

            const cleaned = textarea.value
                .replace(/\r\n/g, '\n')
                .replace(/[ \t]+$/gm, '')
                .replace(/[ \t]{2,}/g, ' ')
                .replace(/\n{3,}/g, '\n\n');

            if (cleaned !== textarea.value) {
                textarea.value = cleaned;
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    });
}

function initCoverToggle() {
    const modeInput = document.querySelector('[data-cover-mode-input]');

    if (!modeInput) {
        return;
    }

    const sync = (mode) => {
        document.querySelectorAll('[data-cover-mode]').forEach((section) => {
            section.classList.toggle('hidden', section.dataset.coverMode !== mode);
        });
        document.querySelectorAll('[data-cover-mode-button]').forEach((tab) => {
            const active = tab.dataset.coverModeButton === mode;
            tab.setAttribute('aria-pressed', active ? 'true' : 'false');
            tab.classList.toggle('bg-emerald-600', active);
            tab.classList.toggle('text-white', active);
            tab.classList.toggle('bg-[#F3ECE0]', !active);
            tab.classList.toggle('border-[#E6DCCF]', !active);
            tab.classList.toggle('text-[#2D231E]', !active);
        });
    };

    document.querySelectorAll('[data-cover-mode-button]').forEach((tab) => {
        tab.addEventListener('click', () => {
            modeInput.value = tab.dataset.coverModeButton;
            sync(modeInput.value);
        });
    });

    const fileInput = document.querySelector('[data-cover-file]');
    const preview = document.querySelector('[data-cover-preview]');

    if (fileInput && preview) {
        fileInput.addEventListener('change', () => {
            if (fileInput.files && fileInput.files[0]) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    preview.src = event.target.result;
                    preview.classList.remove('hidden');
                };
                reader.readAsDataURL(fileInput.files[0]);
            }
        });
    }

    sync(modeInput.value);
}

function initCoverDropzone() {
    const zone = document.querySelector('[data-cover-dropzone]');
    const input = document.querySelector('[data-cover-file]');

    if (!zone || !input) {
        return;
    }

    let depth = 0;

    const setActive = (active) => {
        zone.classList.toggle('border-[#2D231E]', active);
        zone.classList.toggle('bg-white', active);
    };

    zone.addEventListener('dragenter', (event) => {
        event.preventDefault();
        depth += 1;
        setActive(true);
    });

    zone.addEventListener('dragover', (event) => {
        event.preventDefault();
    });

    zone.addEventListener('dragleave', () => {
        depth = Math.max(0, depth - 1);
        if (depth === 0) {
            setActive(false);
        }
    });

    zone.addEventListener('drop', (event) => {
        event.preventDefault();
        depth = 0;
        setActive(false);

        const file = event.dataTransfer ? event.dataTransfer.files[0] : null;

        if (!file || !file.type.startsWith('image/')) {
            return;
        }

        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
}

function initCoverSearch() {
    const button = document.querySelector('[data-cover-search]');
    const results = document.querySelector('[data-cover-search-results]');
    const error = document.querySelector('[data-cover-search-error]');

    if (!button || !results || !error) {
        return;
    }

    const showError = (message) => {
        error.textContent = message;
        error.classList.remove('hidden');
    };

    const hideError = () => {
        error.textContent = '';
        error.classList.add('hidden');
    };

    button.addEventListener('click', async () => {
        const titleInput = document.getElementById('title');
        const authorInput = document.getElementById('author');
        const title = titleInput ? titleInput.value.trim() : '';

        hideError();
        results.classList.add('hidden');
        results.replaceChildren();

        if (!title) {
            showError('Add a title first so Alice knows what to look for.');

            return;
        }

        button.disabled = true;
        button.classList.add('opacity-50', 'cursor-wait');

        try {
            const response = await fetch(button.dataset.coverSearchUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    title: title,
                    author: authorInput ? authorInput.value.trim() : '',
                }),
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Cover search failed. Please try again.');
            }

            if (!result.results.length) {
                showError('No covers found — try a different title, or paste an image URL instead.');

                return;
            }

            const grid = document.createElement('div');
            grid.className = 'grid grid-cols-4 gap-2';

            result.results.forEach((item) => {
                const thumb = document.createElement('button');
                thumb.type = 'button';
                thumb.className = 'group relative rounded-lg border border-[#E6DCCF] overflow-hidden cursor-pointer focus:outline-none focus:ring-2 focus:ring-[#2D231E]';
                thumb.title = item.alt || item.source;

                const img = document.createElement('img');
                img.src = item.url;
                img.alt = item.alt || '';
                img.loading = 'lazy';
                img.className = 'w-full aspect-[3/4] object-cover';
                thumb.appendChild(img);

                const label = document.createElement('span');
                label.textContent = item.source;
                label.className = 'absolute inset-x-0 bottom-0 bg-[#2D231E]/80 text-white text-[9px] font-bold px-1 py-0.5 text-center';
                thumb.appendChild(label);

                thumb.addEventListener('click', () => {
                    const urlInput = document.getElementById('cover_url');
                    const urlTab = document.querySelector('[data-cover-mode-button="url"]');

                    if (urlInput) {
                        urlInput.value = item.url;
                    }

                    if (urlTab) {
                        urlTab.click();
                    }

                    if (urlInput) {
                        urlInput.focus();
                        urlInput.select();
                    }
                });

                grid.appendChild(thumb);
            });

            results.appendChild(grid);
            results.classList.remove('hidden');
        } catch (searchError) {
            showError(searchError.message);
        } finally {
            button.disabled = false;
            button.classList.remove('opacity-50', 'cursor-wait');
        }
    });
}

function initSynopsisFill() {
    const button = document.querySelector('[data-synopsis-fill]');

    if (!button) {
        return;
    }

    const content = document.querySelector('[data-readability-analyze-text="' + button.dataset.synopsisFill + '"]');
    const synopsis = document.querySelector('[data-synopsis-target="' + button.dataset.synopsisFill + '"]');

    if (!content || !synopsis) {
        return;
    }

    button.addEventListener('click', () => {
        const firstParagraph = content.value.trim().split(/\n\s*\n/)[0].trim();
        synopsis.value = firstParagraph.length > 200 ? firstParagraph.slice(0, 197).trimEnd() + '...' : firstParagraph;
    });
}

function initContentImport() {
    const button = document.querySelector('[data-content-import-button]');
    const input = document.querySelector('[data-content-import]');

    if (!button || !input) {
        return;
    }

    const textarea = document.querySelector('[data-readability-analyze-text="' + button.dataset.contentImportButton + '"]');

    if (!textarea) {
        return;
    }

    button.addEventListener('click', () => input.click());

    input.addEventListener('change', () => {
        if (!input.files || !input.files[0]) {
            return;
        }

        const reader = new FileReader();

        reader.onload = (event) => {
            textarea.value = event.target.result;
            textarea.dispatchEvent(new Event('input'));
            input.value = '';
        };

        reader.readAsText(input.files[0]);
    });
}

const GRAMMAR_BUCKET_LABELS = {
    grammar: 'Grammar',
    spelling: 'Spelling',
    punctuation: 'Punctuation',
    style: 'Style',
};

function initGrammarCheck() {
    const button = document.querySelector('[data-grammar-check]');

    if (!button) {
        return;
    }

    const target = button.dataset.grammarCheck;
    const textarea = document.querySelector('[data-grammar-text="' + target + '"]');
    const panel = document.querySelector('[data-grammar-panel="' + target + '"]');
    const summary = document.querySelector('[data-grammar-summary="' + target + '"]');
    const issues = document.querySelector('[data-grammar-issues="' + target + '"]');
    const error = document.querySelector('[data-grammar-error="' + target + '"]');
    const label = document.querySelector('[data-grammar-label="' + target + '"]');
    const busy = document.querySelector('[data-grammar-busy="' + target + '"]');
    const applyAll = document.querySelector('[data-grammar-apply-all="' + target + '"]');
    const filtersEl = document.querySelector('[data-grammar-filters="' + target + '"]');

    if (!textarea || !panel || !summary || !issues || !error || !label || !busy || !applyAll || !filtersEl) {
        return;
    }

    // Boot the on-device grammar engine early (hover/focus), so clicking the
    // button finds it already warm (~2s saved).
    const warmUpEngine = () => {
        import('./grammar-harper.js')
            .then((module) => module.warmUp(button.dataset.grammarBinary))
            .catch(() => {});
    };

    button.addEventListener('pointerenter', warmUpEngine, { once: true });
    button.addEventListener('focus', warmUpEngine, { once: true });

    let currentIssues = [];
    const activeBuckets = new Set(['grammar', 'spelling', 'punctuation', 'style']);

    const chipClass = (active) => (active
        ? 'inline-flex items-center min-h-[30px] px-2.5 bg-emerald-600 text-white rounded-full text-[10px] font-bold cursor-pointer'
        : 'inline-flex items-center min-h-[30px] px-2.5 bg-[#F3ECE0] border border-[#E6DCCF] text-[#2D231E] rounded-full text-[10px] font-bold cursor-pointer hover:border-[#2D231E]');

    const renderFilters = (data) => {
        filtersEl.innerHTML = '';
        filtersEl.classList.toggle('hidden', data.count === 0);

        ['grammar', 'spelling', 'punctuation', 'style'].forEach((bucket) => {
            const count = data.issues.filter((issue) => issue.bucket === bucket).length;

            if (count === 0) {
                return;
            }

            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = chipClass(activeBuckets.has(bucket));
            chip.textContent = GRAMMAR_BUCKET_LABELS[bucket] + ' (' + count + ')';

            chip.addEventListener('click', () => {
                if (activeBuckets.has(bucket)) {
                    activeBuckets.delete(bucket);
                } else {
                    activeBuckets.add(bucket);
                }

                chip.className = chipClass(activeBuckets.has(bucket));
                renderRows();
            });

            filtersEl.append(chip);
        });
    };

    const renderRows = () => {
        issues.innerHTML = '';

        currentIssues
            .filter((issue) => activeBuckets.has(issue.bucket))
            .forEach((issue) => {
                const row = document.createElement('div');
                row.className = 'flex items-start gap-3 p-3 bg-white border border-[#E6DCCF] rounded-lg';

                const text = document.createElement('div');
                text.className = 'min-w-0 flex-1';

                const message = document.createElement('p');
                message.className = 'text-xs font-bold text-[#2D231E]';
                message.textContent = issue.message;

                const meta = document.createElement('p');
                meta.className = 'mt-0.5 text-[10px] font-semibold text-[#2D231E] truncate';
                meta.textContent = issue.category + (issue.context ? ' · "' + issue.context.trim() + '"' : '');

                text.append(message, meta);
                row.append(text);

                if (issue.suggestions.length > 0) {
                    const apply = document.createElement('button');
                    apply.type = 'button';
                    apply.className = 'shrink-0 min-h-[44px] inline-flex items-center px-3 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-[11px] font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer';
                    apply.textContent = 'Apply "' + issue.suggestions[0].slice(0, 24) + '"';

                    apply.addEventListener('click', () => {
                        const before = textarea.value.slice(0, issue.offset);
                        const after = textarea.value.slice(issue.offset + issue.length);
                        textarea.value = before + issue.suggestions[0] + after;
                        textarea.dispatchEvent(new Event('input'));

                        lockAllApplyButtons();
                    });

                    row.append(apply);
                }

                issues.append(row);
            });
    };

    const lockAllApplyButtons = () => {
        issues.querySelectorAll('button').forEach((sibling) => {
            sibling.disabled = true;
            sibling.classList.add('opacity-40', 'pointer-events-none');
        });
        applyAll.disabled = true;
    };

    const render = (data) => {
        currentIssues = data.issues;
        activeBuckets.clear();
        ['grammar', 'spelling', 'punctuation', 'style'].forEach((bucket) => activeBuckets.add(bucket));

        renderFilters(data);
        renderRows();

        if (data.count === 0) {
            label.textContent = 'No issues found';
            summary.textContent = 'Looks good — no grammar or spelling issues found.';
            summary.className = 'text-[11px] font-bold text-right text-emerald-700';
            applyAll.classList.add('hidden');
        } else {
            label.textContent = 'Check grammar · ' + data.count;
            summary.textContent = data.count + ' ' + (data.count === 1 ? 'issue' : 'issues') + ' found — apply each fix or apply them all.';
            summary.className = 'text-[11px] font-bold text-right text-amber-700';
            applyAll.classList.toggle('hidden', !data.issues.some((issue) => issue.suggestions.length > 0));
            applyAll.disabled = false;
            applyAll.textContent = 'Apply all fixes';
        }

        panel.classList.remove('hidden');
        updateInsightsSummary();
    };

    applyAll.addEventListener('click', () => {
        currentIssues
            .filter((issue) => issue.suggestions.length > 0)
            .sort((a, b) => b.offset - a.offset)
            .forEach((issue) => {
                const before = textarea.value.slice(0, issue.offset);
                const after = textarea.value.slice(issue.offset + issue.length);
                textarea.value = before + issue.suggestions[0] + after;
            });

        textarea.dispatchEvent(new Event('input'));
        lockAllApplyButtons();
        applyAll.textContent = 'All applied ✓';
        summary.textContent = 'All fixes applied — re-check to verify.';
        summary.className = 'text-[11px] font-bold text-right text-emerald-700';
        updateInsightsSummary();
    });

    button.addEventListener('click', async () => {
        error.textContent = '';
        error.classList.add('hidden');

        if (textarea.value.trim().length < 20) {
            error.textContent = 'Please enter at least 20 characters to check.';
            error.classList.remove('hidden');

            return;
        }

        const maxChars = Number(button.dataset.grammarMaxChars) || 20000;

        if (textarea.value.length > maxChars) {
            error.textContent = 'The text is too long — keep the check under ' + maxChars.toLocaleString('en-US') + ' characters.';
            error.classList.remove('hidden');

            return;
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        label.classList.add('hidden');
        busy.classList.remove('hidden');

        try {
            // Lazy chunk: harper.js + its worker only load on first check.
            const { checkGrammar } = await import('./grammar-harper.js');
            const data = await checkGrammar(textarea.value, button.dataset.grammarBinary);

            render(data);
        } catch (checkError) {
            error.textContent = checkError.message || 'Grammar check failed. Please try again.';
            error.classList.remove('hidden');
        } finally {
            label.classList.remove('hidden');
            busy.classList.add('hidden');
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    });
}
