from ee.utils import test
from ee.cli.main import get_test_app


class CliTestCaseClean(test.EETestCase):

    def test_ee_cli(self):
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_clean(self):
        self.app = get_test_app(argv=['clean'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_clean_fastcgi(self):
        self.app = get_test_app(argv=['clean', '--fastcgi'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_clean_all(self):
        self.app = get_test_app(argv=['clean', '--all'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_clean_memcache(self):
        self.app = get_test_app(argv=['clean', '--memcache'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_clean_opcache(self):
        self.app = get_test_app(argv=['clean', '--opcache'])
        self.app.setup()
        self.app.run()
        self.app.close()
