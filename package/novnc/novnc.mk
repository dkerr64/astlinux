################################################################################
#
# novnc
#
################################################################################

NOVNC_VERSION = master
NOVNC_SOURCE = $(NOVNC_VERSION).tar.gz
NOVNC_SITE = https://github.com/novnc/noVNC/archive
NOVNC_LICENSE = MPL-2.0 and LGPL-3.0
NOVNC_CONFIGURE_CMDS = echo
NOVNC_BUILD_CMDS = echo

NOVNC_TARGET_DIR=stat/var/www/novnc

define NOVNC_INSTALL_TARGET_CMDS
	mkdir -p $(TARGET_DIR)/$(NOVNC_TARGET_DIR)
	cp $(@D)/*.html $(TARGET_DIR)/$(NOVNC_TARGET_DIR)
	mkdir -p $(TARGET_DIR)/$(NOVNC_TARGET_DIR)/core
	cp -r $(@D)/core $(TARGET_DIR)/$(NOVNC_TARGET_DIR)
	mkdir -p $(TARGET_DIR)/$(NOVNC_TARGET_DIR)/app
	cp -r $(@D)/app $(TARGET_DIR)/$(NOVNC_TARGET_DIR)
	mkdir -p $(TARGET_DIR)/$(NOVNC_TARGET_DIR)/vendor
	cp -r $(@D)/vendor $(TARGET_DIR)/$(NOVNC_TARGET_DIR)
endef

$(eval $(call AUTOTARGETS,package,novnc))
