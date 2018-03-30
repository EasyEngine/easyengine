"""EasyEngine MySQL core classes."""
import pymysql
from pymysql import connections, DatabaseError, Error
import configparser
from os.path import expanduser
import sys
import os
from ee.core.logging import Log
from ee.core.variables import EEVariables


class MySQLConnectionError(Exception):
    """Custom Exception when MySQL server Not Connected"""
    pass


class StatementExcecutionError(Exception):
    """Custom Exception when any Query Fails to execute"""
    pass


class DatabaseNotExistsError(Exception):
    """Custom Exception when Database not Exist"""
    pass


class EEMysql():
    """Method for MySQL connection"""

    def connect(self):
        """Makes connection with MySQL server"""
        try:
            if os.path.exists('/etc/mysql/conf.d/my.cnf'):
                connection = pymysql.connect(read_default_file='/etc/mysql/conf.d/my.cnf')
            else:
                connection = pymysql.connect(read_default_file='~/.my.cnf')
            return connection
        except ValueError as e:
            Log.debug(self, str(e))
            raise MySQLConnectionError
        except pymysql.err.InternalError as e:
            Log.debug(self, str(e))
            raise MySQLConnectionError

    def dbConnection(self, db_name):
        try:
            if os.path.exists('/etc/mysql/conf.d/my.cnf'):
                connection = pymysql.connect(db=db_name,read_default_file='/etc/mysql/conf.d/my.cnf')
            else:
                connection = pymysql.connect(db=db_name,read_default_file='~/.my.cnf')

            return connection
        except DatabaseError as e:
            if e.args[1] == '#42000Unknown database \'{0}\''.format(db_name):
                raise DatabaseNotExistsError
            else:
                raise MySQLConnectionError
        except pymysql.err.InternalError as e:
            Log.debug(self, str(e))
            raise MySQLConnectionError
        except Exception as e :
            Log.debug(self, "[Error]Setting up database: \'" + str(e) + "\'")
            raise MySQLConnectionError

    def execute(self, statement, errormsg='', log=True):
        """Get login details from /etc/mysql/conf.d/my.cnf & Execute MySQL query"""
        connection = EEMysql.connect(self)
        log and Log.debug(self, "Exceuting MySQL Statement : {0}"
                          .format(statement))
        try:
            cursor = connection.cursor()
            sql = statement
            cursor.execute(sql)

            # connection is not autocommit by default.
            # So you must commit to save your changes.
            connection.commit()
        except AttributeError as e:
            Log.debug(self, str(e))
            raise StatementExcecutionError
        except Error as e:
            Log.debug(self, str(e))
            raise StatementExcecutionError
        finally:
            connection.close()

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

    def check_db_exists(self, db_name):
        try:
            if EEMysql.dbConnection(self, db_name):
                return True
        except DatabaseNotExistsError as e:
            Log.debug(self, str(e))
            return False
        except MySQLConnectionError as e:
            Log.debug(self, str(e))
            return False
