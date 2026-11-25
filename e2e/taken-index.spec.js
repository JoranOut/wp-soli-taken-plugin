const { test, expect } = require('@playwright/test');
const { seedIndexFixtures, loginAsAdmin } = require('./helpers');

let fixtures;

test.beforeAll(() => {
	fixtures = seedIndexFixtures();
});

// The fixture page shows four taken per page. Seeded letters: A, B, E (Één…),
// G (the base fixture), four under K, then O, S, T. So page 1 is A–G, page 2
// holds all of K, and page 3 is O–T.
const block = (page) => page.locator('.wp-block-soli-taken-taken-index');
const letters = (page) => block(page).locator('.taken-index__letter h2');
const search = async (page, term) => {
	await block(page).getByRole('searchbox').fill(term);
	await block(page).getByRole('button', { name: 'Search' }).click();
};

test.describe('Taken A–Z index, logged in', () => {
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
		await page.goto(fixtures.pageUrl);
	});

	test('lists every taak on alphabet when nothing is searched', async ({ page }) => {
		await expect(block(page).locator('.taken-index__summary')).toContainText(/All \d+ taken/);
		await expect(letters(page)).toHaveText(['A', 'B', 'E', 'G']);
		await expect(block(page).locator('.taken-index__position')).toHaveText('A–G · page 1 of 3');
	});

	test('files an accented title under its plain letter', async ({ page }) => {
		const e = block(page).locator('.taken-index__letter', { has: page.locator('h2', { hasText: 'E' }) });
		await expect(e).toContainText('Éénmalige klus: piano verhuizen');
	});

	test('keeps a whole letter on one page', async ({ page }) => {
		await block(page).locator('.taken-index__az').getByRole('link', { name: 'K' }).click();

		await expect(letters(page)).toHaveText(['K']);
		await expect(block(page).locator('.taken-index__item h3')).toHaveText([
			'Kampleiding jeugd',
			'Kascommissie',
			'Kerstconcert organisatie',
			'Klusploeg',
		]);
		await expect(block(page).locator('.taken-index__az .is-current')).toHaveText('K');
	});

	test('pages forward and back', async ({ page }) => {
		const pager = block(page).locator('.taken-index__pager');
		await pager.getByRole('link', { name: 'Next →' }).click();
		await pager.getByRole('link', { name: 'Next →' }).click();
		await expect(letters(page)).toHaveText(['O', 'S', 'T']);
		await expect(pager.getByRole('link', { name: 'Next →' })).toHaveCount(0);

		await pager.getByRole('link', { name: '← Previous' }).click();
		await expect(letters(page)).toHaveText(['K']);
	});

	test('shows when and contact from the pattern, apart from the summary', async ({ page }) => {
		await search(page, 'schoonmaakploeg');
		const item = block(page).locator('.taken-index__item');
		await expect(item.locator('p')).toHaveText('Zorgt dat het muziekcentrum er netjes uitziet.');
		await expect(item.locator('.taken-index__meta dt')).toHaveText(['When', 'Contact']);
		await expect(item.locator('.taken-index__meta dd')).toHaveText(['Elke maandag', 'Klaas']);
	});

	test('searches when and contact and marks the match', async ({ page }) => {
		await search(page, 'maandag');
		await expect(block(page).locator('.taken-index__item h3')).toHaveText(['Schoonmaakploeg Muziekcentrum']);
		await expect(block(page).locator('mark')).toHaveText('maandag');

		await search(page, 'Mirjam');
		await expect(block(page).locator('.taken-index__item h3')).toHaveText(['Kerstconcert organisatie']);
	});

	test('ignores case and accents when searching', async ({ page }) => {
		await search(page, 'EENMALIGE');
		await expect(block(page).locator('.taken-index__item h3')).toHaveText(['Éénmalige klus: piano verhuizen']);
	});

	test('requires every word to match', async ({ page }) => {
		await search(page, 'ploeg maandag');
		await expect(block(page).locator('.taken-index__item h3')).toHaveText(['Schoonmaakploeg Muziekcentrum']);
	});

	test('says so when nothing matches and offers the full list', async ({ page }) => {
		await search(page, 'xylofoonstemmer');
		await expect(block(page).locator('.taken-index__none')).toContainText('No taken found for “xylofoonstemmer”');

		await block(page).getByRole('link', { name: 'Show all taken' }).click();
		await expect(letters(page)).toHaveText(['A', 'B', 'E', 'G']);
		await expect(block(page).getByRole('searchbox')).toHaveValue('');
	});
});

test.describe('Taken A–Z index, logged out', () => {
	test('shows a login prompt and no taken', async ({ page }) => {
		const response = await page.goto(fixtures.pageUrl);
		expect(response.status()).toBe(200);
		await expect(block(page)).toContainText('only visible to members');
		await expect(block(page).getByRole('link', { name: 'Log in' })).toBeVisible();
		await expect(page.locator('body')).not.toContainText('Klusploeg');
	});

	test('shows no taken when searched through the url', async ({ page }) => {
		await page.goto(`${fixtures.pageUrl}?taken_zoek=klus`);
		await expect(page.locator('body')).not.toContainText('Klusploeg');
	});
});

test.describe('Taak pattern', () => {
	test('is the starting content of a new taak', async ({ page }) => {
		await loginAsAdmin(page);
		await page.goto('/wp-admin/post-new.php?post_type=soli_taak');
		await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId());

		// The template inserts a core/pattern block, which the editor swaps for the pattern's blocks.
		const classes = await page.waitForFunction(() => {
			const flat = (blocks) => blocks.flatMap((b) => [b, ...flat(b.innerBlocks)]);
			const found = flat(window.wp.data.select('core/block-editor').getBlocks())
				.map((b) => b.attributes.className)
				.filter(Boolean);
			return found.length ? found : null;
		});
		expect(await classes.jsonValue()).toEqual([
			'soli-taak-details',
			'soli-taak-details__when',
			'soli-taak-details__contact',
		]);
	});
});
