"""CLI tests for ee."""

from ee.utils import test
from ee.cli.main import get_test_app


class CliTestCase(test.EETestCase):
    def test_ee_cli(self):
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_create(self):
        self.app = get_test_app(argv=['site', 'create', 'example.com'])
        self.app.setup()
        self.app.run()
        data, output = self.app.last_rendered
        self.eq(output, 'Inside EESiteCreateController.default().\n')
        self.app.close()

    def test_ee_cli_site_update(self):
        self.app = get_test_app(argv=['site', 'update', 'example.com'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_delete(self):
        self.app = get_test_app(argv=['site', 'delete', 'example.com'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_cd(self):
        self.app = get_test_app(argv=['site', 'cd', 'example.com'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_log(self):
        self.app = get_test_app(argv=['site', 'log', 'example.com'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_info(self):
        self.app = get_test_app(argv=['site', 'info', 'example.com'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_enable(self):
        self.app = get_test_app(argv=['site', 'enable', 'example.com'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_disable(self):
        self.app = get_test_app(argv=['site', 'disable', 'example.com'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_edit(self):
        self.app = get_test_app(argv=['site', 'edit', 'example.com'])
        self.app.setup()
        self.app.run()
        self.app.close()
