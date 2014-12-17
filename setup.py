
from setuptools import setup, find_packages
import sys
import os


setup(name='ee',
      version='3.0',
      description="EasyEngine is the commandline tool to manage your Websites"
                  " based on WordPress and NGINX with easy to use commands.",
      long_description="EasyEngine is the commandline tool to manage your "
                       "Websites based on WordPress and NGINX with easy"
                       " to use commands.",
      classifiers=[],
      keywords='',
      author='rtCamp Soultions Pvt. LTD',
      author_email='sys@rtcamp.com',
      url='http://rtcamp.com/easyengine',
      license='GPL',
      packages=find_packages(exclude=['ez_setup', 'examples', 'tests']),
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
          'cement>=2.4',
          'pystache',
          'python-apt',
          ],
      setup_requires=[],
      entry_points="""
          [console_scripts]
          ee = ee.cli.main:main
      """,
      namespace_packages=[],
      )
