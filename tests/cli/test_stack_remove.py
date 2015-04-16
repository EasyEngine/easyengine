from ee.utils import test
from ee.cli.main import get_test_app


class CliTestCaseStack(test.EETestCase):

    def test_ee_cli(self):
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_remove_web(self):
        self.app = get_test_app(argv=['stack', 'remove', '--web'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_install_admin(self):
        self.app = get_test_app(argv=['stack', 'remove', '--admin'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_install_mail(self):
        self.app = get_test_app(argv=['stack', 'remove', '--mail'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_install_nginx(self):
        self.app = get_test_app(argv=['stack', 'remove', '--nginx'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_install_php(self):
        self.app = get_test_app(argv=['stack', 'remove', '--php'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_install_mysql(self):
        self.app = get_test_app(argv=['stack', 'remove', '--mysql'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_install_postfix(self):
        self.app = get_test_app(argv=['stack', 'remove', '--postfix'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_install_wpcli(self):
        self.app = get_test_app(argv=['stack', 'remove', '--wpcli'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_install_phpmyadmin(self):
        self.app = get_test_app(argv=['stack', 'remove', '--phpmyadmin'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_install_adminer(self):
        self.app = get_test_app(argv=['stack', 'remove', '--adminer'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_stack_install_utils(self):
        self.app = get_test_app(argv=['stack', 'remove', '--utils'])
        self.app.setup()
        self.app.run()
        self.app.close()
