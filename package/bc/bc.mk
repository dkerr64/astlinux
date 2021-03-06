#############################################################
#
# bc
#
#############################################################

BC_VERSION = 1.06.95
BC_SOURCE = bc-$(BC_VERSION).tar.bz2
BC_SITE = http://alpha.gnu.org/gnu/bc
BC_DEPENDENCIES = host-bison host-flex

ifeq ($(BR2_PACKAGE_LIBEDIT),y)
BC_DEPENDENCIES += libedit
BC_CONF_OPT += --with-libedit
endif

define BC_INSTALL_TARGET_CMDS
	$(INSTALL) -m 0755 -D $(@D)/bc/bc $(TARGET_DIR)/usr/bin/bc
endef

define BC_UNINSTALL_TARGET_CMDS
	rm -f $(TARGET_DIR)/usr/bin/bc
endef

$(eval $(call AUTOTARGETS,package,bc))
