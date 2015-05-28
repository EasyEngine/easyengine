"""EasyEngine SWAP creation"""
from ee.core.variables import EEVariables
from ee.core.shellexec import EEShellExec
from ee.core.fileutils import EEFileUtils
from ee.core.aptget import EEAptGet
from ee.core.logging import Log
import os


class EESwap():
    """Manage Swap"""

    def __init__():
        """Initialize """
        pass

    def add(self):
        """Swap addition with EasyEngine"""
        if EEVariables.ee_ram < 512:
            if EEVariables.ee_swap < 1000:
                Log.info(self, "Adding SWAP file, please wait...")

                # Install dphys-swapfile
                EEAptGet.update(self)
                EEAptGet.install(self, ["dphys-swapfile"])
                # Stop service
                EEShellExec.cmd_exec(self, "service dphys-swapfile stop")
                # Remove Default swap created
                EEShellExec.cmd_exec(self, "/sbin/dphys-swapfile uninstall")

                # Modify Swap configuration
                if os.path.isfile("/etc/dphys-swapfile"):
                    EEFileUtils.searchreplace(self, "/etc/dphys-swapfile",
                                              "#CONF_SWAPFILE=/var/swap",
                                              "CONF_SWAPFILE=/ee-swapfile")
                    EEFileUtils.searchreplace(self,  "/etc/dphys-swapfile",
                                              "#CONF_MAXSWAP=2048",
                                              "CONF_MAXSWAP=1024")
                    EEFileUtils.searchreplace(self,  "/etc/dphys-swapfile",
                                              "#CONF_SWAPSIZE=",
                                              "CONF_SWAPSIZE=1024")
                else:
                    with open("/etc/dphys-swapfile", 'w') as conffile:
                        conffile.write("CONF_SWAPFILE=/ee-swapfile\n"
                                       "CONF_SWAPSIZE=1024\n"
                                       "CONF_MAXSWAP=1024\n")
                # Create swap file
                EEShellExec.cmd_exec(self, "service dphys-swapfile start")
