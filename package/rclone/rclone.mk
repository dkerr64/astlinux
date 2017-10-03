################################################################################
#
# rclone
#
################################################################################

RCLONE_VERSION = v1.38
RCLONE_SOURCE = rclone-$(RCLONE_VERSION)-linux-amd64.zip
RCLONE_SITE = https://downloads.rclone.org

define RCLONE_EXTRACT_CMDS
	unzip $(DL_DIR)/$(RCLONE_SOURCE) -d $(@D)
endef

define RCLONE_INSTALL_TARGET_CMDS
  cp $(@D)/rclone-$(RCLONE_VERSION)-linux-amd64/rclone $(TARGET_DIR)/usr/bin/rclone
  chmod +x $(TARGET_DIR)/usr/bin/rclone
endef

define RCLONE_UNINSTALL_TARGET_CMDS
  rm -f $(TARGET_DIR)/usr/bin/rclone
endef

$(eval $(call GENTARGETS,package,rclone))
