from ee.utils import test
from ee.cli.main import get_test_app


class CliTestCaseStack(test.EETestCase):

    def test_ee_cli(self):
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_purge_web(self):
        self.app = get_test_app(argv=['stack', 'purge', '--web'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_purge_admin(self):
        self.app = get_test_app(argv=['stack', 'purge', '--admin'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_purge_mail(self):
        self.app = get_test_app(argv=['stack', 'purge', '--mail'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_purge_nginx(self):
        self.app = get_test_app(argv=['stack', 'purge', '--nginx'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_purge_php(self):
        self.app = get_test_app(argv=['stack', 'purge', '--php'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_purge_mysql(self):
        self.app = get_test_app(argv=['stack', 'purge', '--mysql'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_purge_postfix(self):
        self.app = get_test_app(argv=['stack', 'purge', '--postfix'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_purge_wpcli(self):
        self.app = get_test_app(argv=['stack', 'purge', '--wpcli'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_purge_phpmyadmin(self):
        self.app = get_test_app(argv=['stack', 'purge', '--phpmyadmin'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_purge_adminer(self):
        self.app = get_test_app(argv=['stack', 'purge', '--adminer'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_purge_utils(self):
        self.app = get_test_app(argv=['stack', 'purge', '--utils'])
        self.app.setup()
        self.app.run()
        self.app.close()
