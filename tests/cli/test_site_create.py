from ee.utils import test
from ee.cli.main import get_test_app


class CliTestCaseSite(test.EETestCase):

    def test_ee_cli(self):
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_create_html(self):
        self.app = get_test_app(argv=['site', 'create', 'example1.com',
                                      '--html'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_create_php(self):
        self.app = get_test_app(argv=['site', 'create', 'example2.com',
                                      '--php'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_create_mysql(self):
        self.app = get_test_app(argv=['site', 'create', 'example3.com',
                                      '--mysql'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_create_wp(self):
        self.app = get_test_app(argv=['site', 'create', 'example4.com',
                                      '--wp'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_create_wpsubdir(self):
        self.app = get_test_app(argv=['site', 'create', 'example5.com',
                                      '--wpsubdir'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_create_wpsubdomain(self):
        self.app = get_test_app(argv=['site', 'create', 'example6.com',
                                      '--wpsubdomain'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_create_w3tc(self):
        self.app = get_test_app(argv=['site', 'create', 'example7.com',
                                      '--w3tc'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_create_wpfc(self):
        self.app = get_test_app(argv=['site', 'create', 'example8.com',
                                      '--wpfc'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_create_wpsc(self):
        self.app = get_test_app(argv=['site', 'create', 'example9.com',
                                      '--wpsc'])
        self.app.setup()
        self.app.run()
        self.app.close()
