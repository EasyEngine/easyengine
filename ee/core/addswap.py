from ee.core.variables import EEVariables
from ee.core.shellexec import EEShellExec
from ee.core.fileutils import EEFileUtils
from ee.core.logging import Log


class EESwap():
    """Manage Swap"""

    def __init__():
        """Initialize """
        pass

    def add(self):
        if EEVariables.ee_ram < 512:
            if EEVariables.ee_swap < 1000:
                Log.info(self, "Adding SWAP")
                EEShellExec.cmd_exec(self, "dd if=/dev/zero of=/ee-swapfile "
                                     "bs=1024 count=1048k")
                EEShellExec.cmd_exec(self, "mkswap /ee-swapfile")
                EEFileUtils.chown(self, "/ee-swapfile", "root", "root")
                EEFileUtils.chmod(self, "/ee-swapfile", 0o600)
                EEShellExec.cmd_exec(self, "swapon /ee-swapfile")
                with open("/etc/fstab", "a") as swap_file:
                    swap_file.write("/ee-swapfile\tnone\tswap\tsw\t0 0")
