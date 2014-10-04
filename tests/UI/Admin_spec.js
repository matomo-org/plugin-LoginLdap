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
        testEnvironment.configOverride = {
            LoginLdap: {
                servers: ['testserver'],
                new_user_default_sites_view_access: '1,2'
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

    it("should load correctly and allow testing the filter and group fields", function (done) {
        expect.screenshot('admin_page').to.be.captureSelector('#content', function (page) {
            page.load(ldapAdminUrl);

            page.sendKeys('input#memberOf', 'a');
            page.sendKeys('input#filter', 'a');

            page.evaluate(function () {
                $('input#memberOf').val('cn=avengers,dc=avengers,dc=shield,dc=org');
                $('input#filter').val('(objectClass=person)');
            });

            page.click('input#memberOf + .test-config-option-link');
            page.click('input#filter + .test-config-option-link');
        }, done);
    });
});
