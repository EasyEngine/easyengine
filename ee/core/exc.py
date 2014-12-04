"""EasyEngine exception classes."""


class EEError(Exception):
    """Generic errors."""
    def __init__(self, msg):
        Exception.__init__(self)
        self.msg = msg

    def __str__(self):
        return self.msg


class EEConfigError(EEError):
    """Config related errors."""
    pass


class EERuntimeError(EEError):
    """Generic runtime errors."""
    pass


class EEArgumentError(EEError):
    """Argument related errors."""
    pass
