from ee.utils import test
from ee.cli.main import get_test_app


class CliTestCaseDebug(test.EETestCase):

    def test_ee_cli(self):
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_debug_stop(self):
        self.app = get_test_app(argv=['debug', '--stop'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_debug_start(self):
        self.app = get_test_app(argv=['debug', '--start'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_debug_php(self):
        self.app = get_test_app(argv=['debug', '--php'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_debug_nginx(self):
        self.app = get_test_app(argv=['debug', '--nginx'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_debug_rewrite(self):
        self.app = get_test_app(argv=['debug', '--rewrite'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_debug_fpm(self):
        self.app = get_test_app(argv=['debug', '--fpm'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_debug_mysql(self):
        self.app = get_test_app(argv=['debug', '--mysql'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_debug_import_slow_log_interval(self):
        self.app = get_test_app(argv=['debug', '--mysql',
                                      '--import-slow-log-interval'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_debug_site_name_mysql(self):
        self.app = get_test_app(argv=['debug', 'example3.com', '--mysql'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_debug_site_name_wp(self):
        self.app = get_test_app(argv=['debug', 'example4.com', '--wp'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_debug_site_name_nginx(self):
        self.app = get_test_app(argv=['debug', 'example4.com', '--nginx'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_debug_site_name_start(self):
        self.app = get_test_app(argv=['debug', 'example1.com', '--start'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_debug_site_name_stop(self):
        self.app = get_test_app(argv=['debug', 'example1.com', '--stop'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_debug_site_name_rewrite(self):
        self.app = get_test_app(argv=['debug', 'example1.com', '--rewrite'])
        self.app.setup()
        self.app.run()
        self.app.close()
