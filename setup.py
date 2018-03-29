
from setuptools import setup, find_packages
import sys
import os
import glob
import configparser
import re
import shutil

conf = []
templates = []

long_description = '''EasyEngine is the commandline tool to manage your
                      Websites based on WordPress and Nginx with easy to use
                      commands'''

for name in glob.glob('config/plugins.d/*.conf'):
    conf.insert(1, name)

for name in glob.glob('ee/cli/templates/*.mustache'):
    templates.insert(1, name)

if not os.path.exists('/var/log/ee/'):
    os.makedirs('/var/log/ee/')

if not os.path.exists('/var/lib/ee/'):
    os.makedirs('/var/lib/ee/')

# EasyEngine git function
config = configparser.ConfigParser()
config.read(os.path.expanduser("~")+'/.gitconfig')
try:
    ee_user = config['user']['name']
    ee_email = config['user']['email']
except Exception as e:
    print("EasyEngine (ee) required your name & email address to track"
          " changes you made under the Git version control")
    print("EasyEngine (ee) will be able to send you daily reports & alerts in "
          "upcoming version")
    print("EasyEngine (ee) will NEVER send your information across")

    ee_user = input("Enter your name: ")
    while ee_user is "":
        print("Name not Valid, Please enter again")
        ee_user = input("Enter your name: ")

    ee_email = input("Enter your email: ")

    while not re.match(r"^[A-Za-z0-9\.\+_-]+@[A-Za-z0-9\._-]+\.[a-zA-Z]*$",
                       ee_email):
        print("Invalid email address, please try again")
        ee_email = input("Enter your email: ")

    os.system("git config --global user.name {0}".format(ee_user))
    os.system("git config --global user.email {0}".format(ee_email))

if not os.path.isfile('/root/.gitconfig'):
      shutil.copy2(os.path.expanduser("~")+'/.gitconfig', '/root/.gitconfig')

setup(name='ee',
      version='3.7.5',
      description=long_description,
      long_description=long_description,
      classifiers=[],
      keywords='',
      author='rtCamp Soultions Pvt. LTD',
      author_email='ee@rtcamp.com',
      url='http://rtcamp.com/easyengine',
      license='MIT',
      packages=find_packages(exclude=['ez_setup', 'examples', 'tests',
                                      'templates']),
      include_package_data=True,
      zip_safe=False,
      test_suite='nose.collector',
      install_requires=[
          # Required to build documentation
          # "Sphinx >= 1.0",
          # Required for testing
          # "nose",
          # "coverage",
          # Required to function
          'cement == 2.4',
          'pystache',
          'python-apt',
          'pynginxconfig',
          'PyMySQL == 0.8.0',
          'psutil == 3.1.1',
          'sh',
          'sqlalchemy',
          ],
      data_files=[('/etc/ee', ['config/ee.conf']),
                  ('/etc/ee/plugins.d', conf),
                  ('/usr/lib/ee/templates', templates),
                  ('/etc/bash_completion.d/',
                   ['config/bash_completion.d/ee_auto.rc']),
                  ('/usr/share/man/man8/', ['docs/ee.8'])],
      setup_requires=[],
      entry_points="""
          [console_scripts]
          ee = ee.cli.main:main
      """,
      namespace_packages=[],
      )
