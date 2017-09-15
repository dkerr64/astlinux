################################################################################
#
# novnc
#
################################################################################

NOVNC_VERSION = master
NOVNC_SOURCE = $(NOVNC_VERSION).tar.gz
NOVNC_SITE = https://github.com/novnc/noVNC/archive

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

define NOVNC_UNINSTALL_TARGET_CMDS
  rm -rf  $(TARGET_DIR)/$(NOVNC_TARGET_DIR)
endef

$(eval $(call GENTARGETS,package,novnc))
