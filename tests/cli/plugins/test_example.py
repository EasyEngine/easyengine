"""Tests for Example Plugin."""

from ee.utils import test

class ExamplePluginTestCase(test.EETestCase):
    def test_load_example_plugin(self):
        self.app.setup()
        self.app.plugin.load_plugin('example')
