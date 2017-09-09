################################################################################
#
# novnc
#
################################################################################

NOVNC_VERSION = master
NOVNC_SOURCE = $(NOVNC_VERSION).tar.gz
NOVNC_SITE = https://github.com/novnc/noVNC/archive
NOVNC_LICENSE = MPL-2.0 and LGPL-3.0
NOVNC_CONFIGURE_CMDS = "echo"
NOVNC_BUILD_CMDS = "echo"

NOVNC_TARGET_DIR=stat/var/www/novnc

novnc-install:
	echo "here"
	mkdir -p $(TARGET_DIR)/$(NOVNC_TARGET_DIR)
	mkdir -p $(TARGET_DIR)/$(NOVNC_TARGET_DIR)/core
	mkdir -p $(TARGET_DIR)/$(NOVNC_TARGET_DIR)/app


novnc: novnc-install


$(eval $(call AUTOTARGETS,package,novnc))
