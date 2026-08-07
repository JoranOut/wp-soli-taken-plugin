const { test, expect } = require('@playwright/test');
const { seedFixtures, loginAsAdmin, getRestNonce } = require('./helpers');

let fixtures;

test.beforeAll(() => {
	fixtures = seedFixtures();
});

test.describe('logged-out visitors', () => {
	test('get a 403 on the single taak page', async ({ page }) => {
		const response = await page.goto(fixtures.taakUrl);
		expect(response.status()).toBe(403);
		await expect(page.locator('body')).toContainText('only visible to members');
	});

	test('get a 403 on the taken archive', async ({ page }) => {
		const response = await page.goto('/?post_type=soli_taak');
		expect(response.status()).toBe(403);
	});

	test('see no taken in a query loop', async ({ page }) => {
		await page.goto(fixtures.pageUrl);
		await expect(page.locator('body')).toContainText('Taken overzicht');
		await expect(page.locator('body')).not.toContainText('Geheime taak voor leden');
	});

	test('get a 403 from the REST collection and single endpoints', async ({ page }) => {
		const collection = await page.request.get('/?rest_route=/wp/v2/taken');
		expect(collection.status()).toBe(403);

		const single = await page.request.get(
			`/?rest_route=/wp/v2/taken/${fixtures.taakId}`
		);
		expect(single.status()).toBe(403);
	});

	test('do not find taken through site search', async ({ page }) => {
		await page.goto('/?s=Geheime+taak');
		await expect(page.locator('body')).not.toContainText('Geheime taak voor leden');
	});
});

test.describe('logged-in members', () => {
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
	});

	test('can read the single taak page', async ({ page }) => {
		const response = await page.goto(fixtures.taakUrl);
		expect(response.status()).toBe(200);
		await expect(page.locator('body')).toContainText('Geheime taak voor leden');
		await expect(page.locator('body')).toContainText('Alleen voor ingelogde leden bedoeld.');
	});

	test('see taken in a query loop', async ({ page }) => {
		await page.goto(fixtures.pageUrl);
		await expect(
			page.getByRole('link', { name: 'Geheime taak voor leden' }).first()
		).toBeVisible();
	});

	test('can read the REST collection with a nonce, like the editor does', async ({ page }) => {
		const nonce = await getRestNonce(page);
		const collection = await page.request.get('/?rest_route=/wp/v2/taken', {
			headers: { 'X-WP-Nonce': nonce },
		});
		expect(collection.status()).toBe(200);
		const posts = await collection.json();
		expect(posts.some((p) => String(p.id) === String(fixtures.taakId))).toBe(true);
	});
});
