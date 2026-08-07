const { test, expect } = require('@playwright/test');
const { seedFixtures, loginAsAdmin } = require('./helpers');

test.beforeAll(() => {
	seedFixtures();
});

test.describe('Taken post type', () => {
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
	});

	test('shows the Taken menu in wp-admin', async ({ page }) => {
		await page.goto('/wp-admin/');
		await expect(page.locator('#menu-posts-soli_taak')).toBeVisible();
	});

	test('lists the seeded taak in the admin list table', async ({ page }) => {
		await page.goto('/wp-admin/edit.php?post_type=soli_taak');
		await expect(page.locator('.wp-heading-inline')).toHaveText('Taken');
		await expect(
			page.getByRole('link', { name: 'Geheime taak voor leden' }).first()
		).toBeVisible();
	});

	test('registers the Taken Query Loop variation in the editor', async ({ page }) => {
		await page.goto('/wp-admin/post-new.php?post_type=page');

		await page.waitForFunction(
			() =>
				window.wp?.blocks
					?.getBlockVariations('core/query')
					?.some((v) => v.name === 'soli-taken/taken-loop'),
			undefined,
			{ timeout: 30000 }
		);
	});
});
