"""EasyEngine MySQL core classes."""
import pymysql
import configparser
from os.path import expanduser


class EEMysql():
    """Method for MySQL connection"""

    def execute(statement):
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
                print("Unable to connect to database")
                return False

        try:
            cur.execute(statement)
        except Exception as e:
            print("Error occured while executing "+statement)
            cur.close()
            conn.close()
            return False

        cur.close()
        conn.close()

#    def __del__(self):
#        self.cur.close()
#        self.conn.close()
