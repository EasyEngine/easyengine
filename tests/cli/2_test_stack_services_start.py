from ee.utils import test
from ee.cli.main import get_test_app


class CliTestCaseStack(test.EETestCase):

    def test_ee_cli(self):
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_services_start_nginx(self):
        self.app = get_test_app(argv=['stack', 'start', '--nginx'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_services_start_php5_fpm(self):
        self.app = get_test_app(argv=['stack', 'start', '--php'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_services_start_mysql(self):
        self.app = get_test_app(argv=['stack', 'start', '--mysql'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_services_start_postfix(self):
        self.app = get_test_app(argv=['stack', 'start', '--postfix'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_services_start_memcached(self):
        self.app = get_test_app(argv=['stack', 'start', '--memcache'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_services_start_dovecot(self):
        self.app = get_test_app(argv=['stack', 'start', '--dovecot'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_services_start_all(self):
        self.app = get_test_app(argv=['stack', 'start'])
        self.app.setup()
        self.app.run()
        self.app.close()
