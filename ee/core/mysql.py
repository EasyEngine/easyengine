"""EasyEngine MySQL core classes."""
import pymysql
import configparser
from os.path import expanduser
import sys
import os
from ee.core.logging import Log
from ee.core.variables import EEVariables


class EEMysql():
    """Method for MySQL connection"""

    def execute(self, statement, errormsg='', log=True):
        """Get login details from ~/.my.cnf & Execute MySQL query"""
        config = configparser.RawConfigParser()
        cnfpath = expanduser("~")+"/.my.cnf"
        if [cnfpath] == config.read(cnfpath):
            user = config.get('client', 'user')
            passwd = config.get('client', 'password')
            try:
                host = config.get('client', 'host')
            except configparser.NoOptionError as e:
                host = 'localhost'

            try:
                port = config.get('client', 'port')
            except configparser.NoOptionError as e:
                port = '3306'

            try:
                conn = pymysql.connect(host=host, port=int(port),
                                       user=user, passwd=passwd)
                cur = conn.cursor()
            except Exception as e:
                if errormsg:
                    Log.debug(self, '{0}'
                              .format(e))
                    Log.error(self, '{0}'
                              .format(errormsg))
                else:
                    Log.debug(self, '{0}'
                              .format(e))
                    Log.error(self, 'Unable to connect to database: {0}'
                              .format(e))

            try:
                if log:
                    Log.debug(self, "Executing MySQL statement: {0}"
                              .format(statement))

                cur.execute(statement)
                cur.close()
                conn.close()

            except Exception as e:
                cur.close()
                conn.close()
                Log.debug(self, "{0}".format(e))
                if not errormsg:
                    Log.error(self, 'Unable to execute statement')
                else:
                    Log.error(self, '{0}'.format(errormsg))

    def backupAll(self):
        import subprocess
        try:
            Log.info(self, "Backing up database at location: "
                     "/var/ee-mysqlbackup")
            # Setup Nginx common directory
            if not os.path.exists('/var/ee-mysqlbackup'):
                Log.debug(self, 'Creating directory'
                          '/var/ee-mysqlbackup')
                os.makedirs('/var/ee-mysqlbackup')

            db = subprocess.check_output(["mysql -Bse \'show databases\'"],
                                         universal_newlines=True,
                                         shell=True).split('\n')
            for dbs in db:
                if dbs == "":
                    continue
                Log.info(self, "Backing up {0} database".format(dbs))
                p1 = subprocess.Popen("mysqldump {0}"
                                      " --max_allowed_packet=1024M"
                                      " --single-transaction".format(dbs),
                                      stdout=subprocess.PIPE,
                                      stderr=subprocess.PIPE, shell=True)
                p2 = subprocess.Popen("gzip -c > /var/ee-mysqlbackup/{0}{1}.s"
                                      "ql.gz".format(dbs, EEVariables.ee_date),
                                      stdin=p1.stdout,
                                      shell=True)

                # Allow p1 to receive a SIGPIPE if p2 exits
                p1.stdout.close()
                output = p1.stderr.read()
                p1.wait()
                if p1.returncode == 0:
                    Log.debug(self, "done")
                else:
                    Log.error(self, output.decode("utf-8"))
        except Exception as e:
            Log.error(self, "Error: process exited with status %s"
                            % e)
