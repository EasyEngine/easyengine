"""EasyEngine log module"""


class Log:
    HEADER = '\033[95m'
    OKBLUE = '\033[94m'
    OKGREEN = '\033[92m'
    WARNING = '\033[93m'
    FAIL = '\033[91m'
    ENDC = '\033[0m'
    BOLD = '\033[1m'
    UNDERLINE = '\033[4m'

    def error(self, msg):
        print(Log.FAIL + msg + Log.ENDC)
        self.app.log.error(Log.FAIL + msg + Log.ENDC)
        self.app.close(1)

    def info(self, msg, end='\n'):
        print(Log.OKBLUE + msg + Log.ENDC, end=end)
        self.app.log.info(Log.OKBLUE + msg + Log.ENDC)

    def warn(self, msg):
        self.app.log.warn(Log.BOLD + msg + Log.ENDC)

    def debug(self, msg):
        self.app.log.debug(Log.HEADER + msg + Log.ENDC)
