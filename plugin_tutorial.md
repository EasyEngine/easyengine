
# EasyEngine Plugin Tutorial

In a `Cement App`(a command line utitily built on `Cement Framework`) a plugin is any piece of python code that can be enabled/disabled  through its configuraton present in `plugin configuration file` , if enabled , these piece(s) are run during the app build/integration process, else not. Typically these piece(s) contains one or more `Cement Controller` definition and their registration with the `app`.


**How does a Cement App locate its Plugins(`.py` files) and Plugin Configuration Files(`.conf` files)**

 1. Every Cement App searches for plugins(.py files) in directories whose path is listed in the python List `CementApp.Meta.plugin_dirs`
 2. Similarly, for  plugin configuration files(.conf files)  is `CementApp.Meta.plugin_config_dirs`
```python
class EEApp(foundation.CementApp):
    class Meta:
        label = 'ee'
        # list of paths where this app looks for plugins(.py files)
        # if not set, is set by app during setup by default to the first two entries shown here 
        plugin_config_dirs=[
            '/etc/ee/plugins.d',
            '~/.ee/plugins.d',
            '/vagrant/test/easyengine/plugins/myplugin/config/plugins.d',
            ]
        # list of paths where this app looks for plugin configuration files(.conf files)
        # if not set, is set by app during setup by default to the first two entries shown here
        plugin_dirs=[
            '/usr/lib/ee/plugins',
            '~/.ee/plugins',
            '/vagrant/test/easyengine/plugins/myplugin/plugins',
            ]
```


**Coding a plugin**



 1. `Boss` can be used to standardize and automate the task of creating directory structure and template for your new plugin , [see this for more.](http://docs.rtcamp.com/easyengine/dev/plugins/)

 2. A sample plugin. let this be `firstdemoplugn.py` file(must exist in any of path listed in `CementApp.Meta.plugin_dirs`).
  ```python
  from cement.core import handler
  from cement.core.controller import CementBaseController, expose
  class EEDemoPluginController(CementBaseController):
      class Meta:
          label = "firstdemoplugin"
          description = "First Demo Plugin for Easyengine."
          stacked_on = 'base'
          stacked_type = 'embedded'
          arguments = [
              (['--foo'],
               dict(action='store', help='the infamous foo option')),
              ]
      @expose(help="demo plugin")
      def demo(self):
          print("this is result of running 'ee demo' command")
          if self.app.pargs.foo:
              print("Received option: foo => %s" % self.app.pargs.foo)
  def load(app):
      handler.register(EEDemoPluginController)
  ```
 3. Configuration of above Plugin(can be written in any of the `.conf` files present on path listed in `CementApp.Meta.plugin_config_dirs`). To enable the `firstdemoplugn` plugin the configuration goes as follows.
```python
# name of plugin file without extension, enclosed in rect. braces, plugin's config. follows after it .
[firstdemoplugn]
# if true enables the plugin , if false disables it
enable_plugin = true
```
