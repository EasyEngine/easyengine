from ee.utils import test
from ee.cli.main import get_test_app


class CliTestCaseStack(test.EETestCase):

    def test_ee_cli(self):
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_services_status_nginx(self):
        self.app = get_test_app(argv=['stack', 'status', '--nginx'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_services_status_php5_fpm(self):
        self.app = get_test_app(argv=['stack', 'status', '--php'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_services_status_mysql(self):
        self.app = get_test_app(argv=['stack', 'status', '--mysql'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_services_status_postfix(self):
        self.app = get_test_app(argv=['stack', 'status', '--postfix'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_services_status_memcached(self):
        self.app = get_test_app(argv=['stack', 'status', '--memcache'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_services_status_dovecot(self):
        self.app = get_test_app(argv=['stack', 'status', '--dovecot'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_services_status_all(self):
        self.app = get_test_app(argv=['stack', 'status'])
        self.app.setup()
        self.app.run()
        self.app.close()
