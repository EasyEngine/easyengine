# Based on https://github.com/datafolklabs/cement/issues/295
# To avoid encoding releated error,we defined our custom output handler
# I hope we will remove this when we upgarde to Cement 2.6 (Not released yet)
import os
from cement.utils import fs
from cement.ext.ext_mustache import MustacheOutputHandler


class EEOutputHandler(MustacheOutputHandler):
    class Meta:
        label = 'ee_output_handler'

    def _load_template_from_file(self, path):
        for templ_dir in self.app._meta.template_dirs:
            full_path = fs.abspath(os.path.join(templ_dir, path))
            if os.path.exists(full_path):
                self.app.log.debug('loading template file %s' % full_path)
                return open(full_path, encoding='utf-8', mode='r').read()
            else:
                continue
