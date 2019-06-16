/*!
 * Piwik - free/libre analytics platform
 *
 * LoginLdap admin page screenshot tests.
 *
 * @link http://piwik.org
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
                admin_pass: 'secrets'
            }
        };
        testEnvironment.save();
    });

    var ldapAdminUrl = "?module=LoginLdap&action=admin&idSite=1&period=day&date=yesterday";

    it("should load correctly and allow testing the filter and group fields", async function () {
        await page.goto(ldapAdminUrl);
        await page.waitForFunction("$('input[name=required_member_of]').length > 0");

        await page.evaluate(function () {
            $('input#required_member_of').val('cn=avengers,dc=avengers,dc=shield,dc=org').trigger('input');
            $('input#ldap_user_filter').val('(objectClass=person)').trigger('input');
        });

        await page.evaluate(function () {
            $('[piwik-login-ldap-testable-field] [piwik-save-button] input').click();
        });

        await page.waitForNetworkIdle();

        var elem = await page.jQuery('#content');
        expect(await elem.screenshot()).to.matchImage('admin_page');
    });
});
