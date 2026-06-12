/*!
 * Matomo - free/libre analytics platform
 *
 * LoginLdap admin page screenshot tests.
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

describe("LoginLdap_Admin", function () {
    this.timeout(0);

    this.fixture = "Piwik\\Tests\\Fixtures\\OneVisitorTwoVisits";

    before(function () {
        testEnvironment.pluginsToLoad = ['LoginLdap'];
        testEnvironment.configOverride = {
            LoginLdap: {
                servers: ['testserver'],
                new_user_default_sites_view_access: '1,2',
                enable_synchronize_access_from_ldap: 1
            },
            LoginLdap_testserver: {
                hostname: 'localhost',
                port: 389,
                base_dn: 'dc=avengers,dc=shield,dc=org',
                admin_user: 'cn=fury,dc=avengers,dc=shield,dc=org',
                admin_pass: 'secrets',
                start_tls: false
            }
        };
        testEnvironment.save();
    });

    var ldapAdminUrl = "?module=LoginLdap&action=admin&idSite=1&period=day&date=yesterday";
    var addNewTokenUrl = "?module=UsersManager&action=addNewToken&idSite=1&period=day&date=yesterday";

    it("should load correctly and allow testing the filter and group fields", async function () {
        await page.goto(ldapAdminUrl);
        await page.waitForFunction("$('input[name=required_member_of]').length > 0");

        await page.evaluate(function () {
            $('input#required_member_of').val('cn=avengers,dc=avengers,dc=shield,dc=org').trigger('change');
            $('input#ldap_user_filter').val('(objectClass=person)').trigger('change');
        });

        await page.evaluate(function () {
            $('.loginLdapTestableField .matomo-save-button input').click();
        });

        await page.waitForNetworkIdle();

        var elem = await page.jQuery('#content');
        expect(await elem.screenshot()).to.matchImage('admin_page');
    });

    it("should show the password confirmation screen when add new token screen is called", async function () {
        testEnvironment.configOverride.LoginLdap = { enable_password_confirmation: 1 };
        testEnvironment.save();
        await page.goto(addNewTokenUrl);
        await page.waitForNetworkIdle();
        var elem = await page.jQuery('#loginPage');

        // Assert that the button starts off disabled.
        await page.waitForSelector('#login_form_submit[disabled]');

        // Button enabled with password entered.
        await page.type('#login_form_password', 'p');
        await page.waitForSelector('#login_form_submit:not([disabled])');

        expect(await elem.screenshot()).to.matchImage('addNewToken_with_password');
    });

    it("should still show the password confirmation screen for non-LDAP users when password confirmation is disabled", async function () {
        testEnvironment.configOverride.LoginLdap = { enable_password_confirmation: 0 };
        testEnvironment.save();
        await page.goto(addNewTokenUrl);
        await page.waitForNetworkIdle();
        var elem = await page.jQuery('#loginPage');
        await page.waitForSelector('#login_form_submit[disabled]');
        expect(await elem.screenshot()).to.matchImage('addNewToken_with_password_non_ldap_user');
    });

    it("should require password confirmation when saving LDAP settings from the admin page", async function () {
        testEnvironment.configOverride.LoginLdap = {
            servers: ['testserver'],
            new_user_default_sites_view_access: '1,2',
            enable_synchronize_access_from_ldap: 1,
            enable_password_confirmation: 1,
        };
        testEnvironment.save();

        await page.goto(ldapAdminUrl);
        await page.waitForFunction("$('input[name=required_member_of]').length > 0");

        await page.evaluate(function () {
            $('.matomo-save-button input').eq(0).click();
        });

        await page.waitForSelector('.confirm-password-modal.modal.open', { visible: true });
    });

    it("should require password confirmation when saving LDAP servers from the admin page", async function () {
        testEnvironment.configOverride.LoginLdap = {
            servers: ['testserver'],
            new_user_default_sites_view_access: '1,2',
            enable_synchronize_access_from_ldap: 1,
            enable_password_confirmation: 1,
        };
        testEnvironment.save();

        await page.goto(ldapAdminUrl);
        await page.waitForFunction("$('input[name=required_member_of]').length > 0");

        await page.evaluate(function () {
            $('.matomo-save-button input').last().click();
        });

        await page.waitForSelector('.confirm-password-modal.modal.open', { visible: true });
    });
});
