const { test, expect } = require('@playwright/test');
const { seedFixtures, loginAsAdmin } = require('./helpers');

let fixtures;

test.beforeAll(() => {
	fixtures = seedFixtures();
});

/**
 * WP_DEBUG and WP_DEBUG_DISPLAY are enabled for the tests environment in
 * .wp-env.json, so PHP diagnostics are rendered into the page body.
 *
 * Fatals and parse errors are never acceptable, wherever they come from.
 * Softer diagnostics are only asserted for this plugin's own files, so an
 * unrelated WordPress core deprecation cannot turn CI red.
 */
const FATAL = /Fatal error|Parse error/i;
const OWN_FILES =
	/(Warning|Notice|Deprecated):[^\n]*(wp-soli-taken-plugin\.php|class-soli-taken-(post-type|visibility)\.php)/i;

async function expectNoPhpErrors(page) {
	// textContent, never innerText. innerText reflects *rendered* text and
	// silently skips anything hidden (display:none, collapsed panels, screens a
	// script reveals later), so a PHP diagnostic emitted inside a hidden
	// container makes these assertions pass for the wrong reason. Measured here:
	// an undefined-variable warning echoed inside a display:none div fails 3
	// tests with textContent and 0 with innerText. Do not change this back.
	//
	// The two patterns need different reads, so the body is read twice in one
	// evaluate:
	//
	// - OWN_FILES matches within a single line ([^\n]*), and textContent also
	//   returns the source text of <script> and <style>. wp-admin prints large
	//   one-line JSON blobs into inline script, so a string containing
	//   'Warning:' near a plugin path would match there and turn CI red for
	//   nothing. That pattern must NOT see script text, so it reads a body
	//   clone with script/style/template/noscript stripped.
	// - FATAL must see script text. A fatal thrown while an inline script is
	//   being printed lands inside that <script> node, and a stripped clone
	//   would lose it. So it reads the full body.
	const { full, markup } = await page.evaluate(() => {
		const clone = document.body.cloneNode(true);
		clone
			.querySelectorAll('script, style, template, noscript')
			.forEach((node) => node.remove());

		return {
			full: document.body.textContent || '',
			markup: clone.textContent || '',
		};
	});

	expect(full).not.toMatch(FATAL);
	expect(markup).not.toMatch(OWN_FILES);
}

test.describe('renders without PHP errors', () => {
	// Logged in throughout: logged-out visitors get a 403 on every taken
	// surface, which would render the error assertions meaningless.
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
	});

	test('on the single taak page', async ({ page }) => {
		await page.goto(fixtures.taakUrl);
		await expectNoPhpErrors(page);
	});

	test('on the taken archive', async ({ page }) => {
		await page.goto('/?post_type=soli_taak');
		await expectNoPhpErrors(page);
	});

	test('on a page with the taken query loop', async ({ page }) => {
		await page.goto(fixtures.pageUrl);
		await expect(page.locator('body')).toContainText('Geheime taak voor leden');
		await expectNoPhpErrors(page);
	});

	test('in the wp-admin list table', async ({ page }) => {
		await page.goto('/wp-admin/edit.php?post_type=soli_taak');
		await expectNoPhpErrors(page);
	});

	test('in the block editor for a taak', async ({ page }) => {
		await page.goto(`/wp-admin/post.php?post=${fixtures.taakId}&action=edit`);
		await expectNoPhpErrors(page);
	});
});
