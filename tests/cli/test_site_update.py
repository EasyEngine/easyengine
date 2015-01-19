from ee.utils import test
from ee.cli.main import get_test_app


class CliTestCaseSite(test.EETestCase):

    def test_ee_cli(self):
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_update(self):
        self.app = get_test_app(argv=['site', 'update', 'site_name',
                                      '--password'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_update_html(self):
        self.app = get_test_app(argv=['site', 'update', 'site_name', '--html'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_update_php(self):
        self.app = get_test_app(argv=['site', 'update', 'site_name', '--php'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_update_mysql(self):
        self.app = get_test_app(argv=['site', 'update', 'site_name',
                                      '--mysql'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_update_wp(self):
        self.app = get_test_app(argv=['site', 'update', 'site_name', '--wp'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_update_wpsubdir(self):
        self.app = get_test_app(argv=['site', 'update', 'site_name',
                                      '--wpsubdir'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_update_wpsubdomain(self):
        self.app = get_test_app(argv=['site', 'update', 'site_name',
                                      '--wpsubdomain'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_update_w3tc(self):
        self.app = get_test_app(argv=['site', 'update', 'site_name', '--w3tc'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_update_wpfc(self):
        self.app = get_test_app(argv=['site', 'update', 'site_name', '--wpfc'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_update_wpsc(self):
        self.app = get_test_app(argv=['site', 'update', 'site_name', '--wpsc'])
        self.app.setup()
        self.app.run()
        self.app.close()
