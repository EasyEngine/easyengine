from ee.utils import test
from ee.cli.main import get_test_app


class CliTestCaseSecure(test.EETestCase):

    def test_ee_cli(self):
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_secure_auth(self):
        self.app = get_test_app(argv=['secure', '--auth'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_secure_port(self):
        self.app = get_test_app(argv=['secure', '--port'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_secure_ip(self):
        self.app = get_test_app(argv=['secure', '--ip'])
        self.app.setup()
        self.app.run()
        self.app.close()
