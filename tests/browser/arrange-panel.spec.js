import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';

/**
 * MISSIONS tab as the answer sheet: the tiled arrange move and the paginated
 * choice move both render inside the journal tab (no floating overlays), and
 * the tab shows an empty state when nothing is running. The seed prints the
 * answers from the server payload; the tests use them only to build correct
 * tap sequences.
 */

let seed;

function reseed() {
    const out = execFileSync(
        'php',
        ['artisan', 'tinker', '--execute', "require base_path('tests/browser/seed-arrange.php');"],
        { cwd: process.cwd(), encoding: 'utf8' },
    );
    const match = out.match(/ARRANGE_SEED (\{.*\})/);
    if (!match) {
        throw new Error(`Seed did not print a marker. Output:\n${out}`);
    }
    seed = JSON.parse(match[1]);
}

test.beforeAll(() => reseed());

async function login(page) {
    await page.goto('/login');
    const loginForm = page.locator('form[action$="/login"]');
    await loginForm.locator('input[name="email"]').fill(seed.email);
    await loginForm.locator('input[name="password"]').fill(seed.password);
    await loginForm.locator('button[type="submit"]').click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 15_000 });
    await page.goto(`/read/${seed.slug}`);
    await page.locator('[data-journal-open]').click();
}

test('find moves answer in the story; MISSIONS mirrors the rule as a task card', async ({ page }) => {
    await login(page);

    const findRow = page.locator('.journal-action-row', { hasText: 'words starting with C' });
    await expect(findRow).toBeVisible();
    await findRow.locator('[data-go]').click();

    // Find moves close the journal so the story is the game surface.
    await expect(page.locator('#journal-panel')).toBeHidden();
    await expect(page.locator('body')).toHaveAttribute('data-game-mode', 'on');
    await expect(page.locator('[data-gm-task]')).toHaveText('FOUND WORDS 0/1');

    // Reopening lands on MISSIONS with the read-only task card.
    await page.locator('[data-journal-open]').click();
    await expect(page.locator('[data-journal-tab="missions"]')).toHaveClass(/is-active/);
    await expect(page.locator('[data-missions-sheet]')).toBeVisible();
    await expect(page.locator('[data-missions-progress]')).toHaveText('0/1');

    // Close again and tap the matching word in the story: auto-submit wins.
    await page.keyboard.press('Escape');
    await expect(page.locator('#journal-panel')).toBeHidden();
    await page.locator('.tap-word', { hasText: /^castle$/ }).click();

    await expect(page.locator('[data-journal-tab="battle"]')).toHaveClass(/is-active/);
    await expect(page.locator('body')).not.toHaveAttribute('data-game-mode', 'on');
    await expect(page.locator('.journal-action-row.is-done', { hasText: 'words starting with C' })).toBeVisible();
});

test('arrange answers in the MISSIONS tab: empty state, place, remove, wrong then right', async ({ page }) => {
    await login(page);

    // Tab order is BATTLE, MISSIONS, SKINS; MISSIONS starts on the empty state.
    const tabs = page.locator('[data-journal-tab]');
    await expect(tabs.nth(0)).toHaveText('BATTLE');
    await expect(tabs.nth(1)).toHaveText('MISSIONS');
    await expect(tabs.nth(2)).toHaveText('SKINS');
    await tabs.nth(1).click();
    await expect(page.locator('[data-missions-empty]')).toBeVisible();
    await expect(page.locator('[data-missions-sheet]')).toBeHidden();

    // No gray text anywhere on the empty state (or in the sheet's hints).
    await expect(page.locator('[data-missions-empty] .missions-empty-title')).toHaveCSS('color', 'rgb(0, 0, 0)');
    await expect(page.locator('[data-missions-empty] .missions-empty-sub')).toHaveCSS('color', 'rgb(0, 0, 0)');
    const hintColor = await page.evaluate(() => {
        const probe = document.createElement('div');
        probe.className = 'gm-row-hint';
        document.body.appendChild(probe);
        const color = getComputedStyle(probe).color;
        probe.remove();
        return color;
    });
    expect(hintColor).toBe('rgb(0, 0, 0)');

    await tabs.nth(0).click();

    const arrangeRow = page.locator('.journal-action-row', { hasText: 'LETTER SCRAMBLE' });
    await expect(arrangeRow).toBeVisible();
    await arrangeRow.locator('[data-go]').click();

    // The journal stays open on MISSIONS with the sheet; the overlay is gone.
    await expect(page.locator('[data-journal-tab="missions"]')).toHaveClass(/is-active/);
    await expect(page.locator('[data-missions-sheet]')).toBeVisible();
    await expect(page.locator('#game-mode')).toHaveCount(0);
    await expect(page.locator('body')).toHaveAttribute('data-game-mode', 'on');

    const tray = page.locator('.gm-arrange-tray .gm-tile');
    const slots = page.locator('.gm-arrange-slots .gm-tile');
    const answer = seed.answers[0];
    const trayLetters = await tray.allTextContents();

    expect(trayLetters.join('')).toHaveLength(answer.length);

    // Each word shows its full slot row up front (dashed empty boxes) plus a
    // WORD n / 0-of-length header: the word length itself is the hint.
    const firstRow = page.locator('.gm-arrange-row').first();
    await expect(firstRow.locator('.gm-slot')).toHaveCount(answer.length);
    await expect(firstRow.locator('.gm-arrange-progress')).toHaveText(`0/${answer.length}`);

    // Place two tiles, then remove one: the slot count goes 2 -> 1.
    await tray.nth(0).click();
    await tray.nth(1).click();
    await expect(slots).toHaveCount(2);
    await expect(firstRow.locator('.gm-arrange-progress')).toHaveText(`2/${answer.length}`);
    await slots.first().click();
    await expect(slots).toHaveCount(1);

    // Clear the rest, then build a wrong order from the reversed tray (or the
    // tray order itself if the reverse happens to spell the answer).
    while ((await slots.count()) > 0) {
        await slots.first().click();
    }
    const reversed = [...trayLetters].reverse().join('');
    const wrongOrder = reversed === answer ? [...trayLetters] : [...trayLetters].reverse();
    for (const letter of wrongOrder) {
        await page.locator('.gm-arrange-tray .gm-tile:not(.is-used)', { hasText: new RegExp(`^${letter}$`) }).first().click();
    }
    await page.locator('.missions-sheet .gm-submit').click();

    await expect(page.locator('[data-gm-note]')).toHaveText('Not quite. Try again.');
    await expect(page.locator('[data-missions-sheet]')).toBeVisible();

    // Clear again and spell the right word in order.
    while ((await slots.count()) > 0) {
        await slots.first().click();
    }
    for (const letter of answer) {
        await page.locator('.gm-arrange-tray .gm-tile:not(.is-used)', { hasText: new RegExp(`^${letter}$`) }).first().click();
    }
    await page.locator('.missions-sheet .gm-submit').click();

    // Correct: back to BATTLE with the move done; MISSIONS is empty again.
    await expect(page.locator('[data-journal-tab="battle"]')).toHaveClass(/is-active/);
    await expect(page.locator('body')).not.toHaveAttribute('data-game-mode', 'on');
    await expect(page.locator('.journal-action-row.is-done', { hasText: 'LETTER SCRAMBLE' })).toBeVisible();
    await page.locator('[data-journal-tab="missions"]').click();
    await expect(page.locator('[data-missions-empty]')).toBeVisible();
    await expect(page.locator('[data-missions-sheet]')).toBeHidden();
});

test('choice moves paginate one item per page: back and forth, CHECK on the last', async ({ page }) => {
    await login(page);

    const meaningRow = page.locator('.journal-action-row', { hasText: 'MEANING STRIKE' });
    await expect(meaningRow).toBeVisible();
    await meaningRow.locator('[data-go]').click();

    await expect(page.locator('[data-journal-tab="missions"]')).toHaveClass(/is-active/);

    const visibleGroup = page.locator('.gm-rows > [data-choice-group]:visible');
    const back = page.locator('[data-pager-back]');
    const next = page.locator('[data-pager-next]');

    // One question per page: page 0 first, BACK dead and NEXT locked until answered.
    await expect(visibleGroup).toHaveCount(1);
    await expect(visibleGroup).toHaveAttribute('data-choice-group', '0');
    // The question line must be solid black, never gray.
    await expect(visibleGroup.locator('.gm-choice-q')).toHaveCSS('color', 'rgb(0, 0, 0)');
    await expect(back).toBeDisabled();
    await expect(next).toHaveText('NEXT');
    await expect(next).toBeDisabled();

    // Answer, advance, come back: the pick survives the round trip.
    await visibleGroup.locator('.gm-choice-btn').first().click();
    await expect(next).toBeEnabled();
    await next.click();
    await expect(visibleGroup).toHaveAttribute('data-choice-group', '1');
    await expect(back).toBeEnabled();
    await back.click();
    await expect(visibleGroup).toHaveAttribute('data-choice-group', '0');
    await expect(visibleGroup.locator('.gm-choice-btn.is-picked')).toHaveCount(1);

    // Answer every page with the server's answers; the last page shows CHECK.
    const answers = seed.defmatchAnswers;
    expect(answers).toHaveLength(3);
    for (let i = 0; i < answers.length; i++) {
        const group = page.locator(`[data-choice-group="${i}"]:visible`);
        await group.locator('.gm-choice-btn', { hasText: new RegExp(`^${answers[i]}$`) }).click();
        if (i < answers.length - 1) {
            await expect(next).toHaveText('NEXT');
            await next.click();
        }
    }
    await expect(next).toHaveText('CHECK');
    await next.click();

    await expect(page.locator('[data-journal-tab="battle"]')).toHaveClass(/is-active/);
    await expect(page.locator('.journal-action-row.is-done', { hasText: 'MEANING STRIKE' })).toBeVisible();
});

test('quitting a move from the strip clears the MISSIONS sheet', async ({ page }) => {
    reseed();
    await login(page);

    const arrangeRow = page.locator('.journal-action-row', { hasText: 'LETTER SCRAMBLE' });
    await arrangeRow.locator('[data-go]').click();
    await expect(page.locator('[data-missions-sheet]')).toBeVisible();

    // Quit mid-move from the top-bar strip (costs a heart); the open journal
    // backdrop covers the strip, so close it first.
    await page.keyboard.press('Escape');
    await expect(page.locator('#journal-panel')).toBeHidden();
    await page.locator('[data-gm-quit]').click();
    await expect(page.locator('body')).not.toHaveAttribute('data-game-mode', 'on');
    await expect(page.locator('[data-journal-tab="battle"]')).toHaveClass(/is-active/);
    await page.waitForTimeout(1500); // boss punch settles back on BATTLE

    // MISSIONS must fall back to the empty state, never the dead sheet.
    await page.locator('[data-journal-tab="missions"]').click();
    await expect(page.locator('[data-missions-sheet]')).toBeHidden();
    await expect(page.locator('[data-missions-sheet]')).toHaveText('');
    await expect(page.locator('[data-missions-empty]')).toBeVisible();
});

test('an active row offers RESUME only: no timer, no X, and resume reopens the sheet', async ({ page }) => {
    reseed();
    await login(page);

    const arrangeRow = page.locator('.journal-action-row', { hasText: 'LETTER SCRAMBLE' });
    await arrangeRow.locator('[data-go]').click();
    await expect(page.locator('[data-missions-sheet]')).toBeVisible();

    // Back on BATTLE: the started row shows exactly one control - RESUME.
    await page.locator('[data-journal-tab="battle"]').click();
    const activeRow = page.locator('.journal-action-row.is-active');
    await expect(activeRow).toBeVisible();
    await expect(activeRow.locator('[data-go]')).toHaveText('RESUME');
    await expect(activeRow.locator('.journal-row-timer')).toHaveCount(0);
    await expect(activeRow.locator('[data-row-quit]')).toHaveCount(0);

    // RESUME reopens the sheet with the move still live.
    await activeRow.locator('[data-go]').click();
    await expect(page.locator('[data-journal-tab="missions"]')).toHaveClass(/is-active/);
    await expect(page.locator('[data-missions-sheet]')).toBeVisible();
});

test('journal help: the ? button opens HOW TO FINISH?, the sidebar switches sections and ACTIONS paginates', async ({ page }) => {
    await login(page);

    // The ? button sits beside the X in the journal header and opens the modal.
    const opener = page.locator('.journal-top-btns [data-modal-open="journal-help-modal"]');
    const modal = page.locator('#journal-help-modal');
    await expect(opener).toHaveText('?');
    await opener.click();
    await expect(modal).toBeVisible();
    await expect(page.locator('#journal-help-modal-title')).toHaveText('HOW TO FINISH?');

    // Overview is the landing section; the other sections stay hidden.
    await expect(modal.locator('[data-jh-panel="overview"]')).toBeVisible();
    await expect(modal.locator('[data-jh-panel="actions"]')).toBeHidden();

    // ACTIONS shows one action type at a time, five pages, Prev/Next rules.
    await modal.locator('[data-jh-tab="actions"]').click();
    await expect(modal.locator('[data-jh-panel="actions"]')).toBeVisible();
    await expect(modal.locator('[data-jh-panel="overview"]')).toBeHidden();
    await expect(modal.locator('[data-jh-page]')).toHaveCount(5);
    await expect(modal.locator('[data-jh-page]:visible')).toHaveCount(1);
    await expect(modal.locator('[data-jh-prev]')).toBeHidden();
    await expect(modal.locator('[data-jh-page]:visible')).toContainText('SPELL STRIKE');
    for (let i = 0; i < 4; i++) {
        await modal.locator('[data-jh-next]').click();
        await expect(modal.locator('[data-jh-page]:visible')).toHaveCount(1);
    }
    await expect(modal.locator('[data-jh-next]')).toBeHidden();
    await expect(modal.locator('[data-jh-page]:visible')).toContainText('LETTER SCRAMBLE');

    // The remaining sidebar sections switch in.
    await modal.locator('[data-jh-tab="missions"]').click();
    await expect(modal.locator('[data-jh-panel="missions"]')).toBeVisible();
    await modal.locator('[data-jh-tab="skins"]').click();
    await expect(modal.locator('[data-jh-panel="skins"]')).toBeVisible();
    await expect(modal.locator('[data-jh-panel="actions"]')).toBeHidden();

    // Escape closes the help modal only: the journal stays open.
    await page.keyboard.press('Escape');
    await expect(modal).toBeHidden();
    await expect(page.locator('#journal-panel')).toBeVisible();

    // Reopening resets to Overview (the pager reset rides the same event).
    await opener.click();
    await expect(modal.locator('[data-jh-panel="overview"]')).toBeVisible();
    await expect(modal.locator('[data-jh-tab="overview"]')).toHaveClass(/is-active/);
});
