################################################################################
#
# acme
#
################################################################################

ACME_VERSION = 2.8.7
ACME_SOURCE = acme.sh-$(ACME_VERSION).tar.gz
ACME_SITE = https://github.com/acmesh-official/acme.sh/archive/$(ACME_VERSION)

ACME_REMOVE_TARGET_DNSAPI = dns_myapi.sh dns_lexicon.sh dns_cyon.sh dns_inwx.sh $(if $(BR2_PACKAGE_BIND),,dns_nsupdate.sh) README.md
ACME_REMOVE_TARGET_DNSAPI += dns_ispconfig.sh dns_do.sh dns_knot.sh dns_gcloud.sh dns_mydevil.sh dns_maradns.sh dns_pleskxml.sh

define ACME_INSTALL_TARGET_CMDS
	$(INSTALL) -D -m 0644 package/acme/deploy/astlinux.sh $(TARGET_DIR)/stat/etc/acme/deploy/astlinux.sh
	$(INSTALL) -D -m 0644 package/acme/deploy/custom.sh $(TARGET_DIR)/stat/etc/acme/deploy/custom.sh
	$(INSTALL) -D -m 0644 $(@D)/deploy/ssh.sh $(TARGET_DIR)/stat/etc/acme/deploy/ssh.sh
	$(INSTALL) -D -m 0755 package/acme/acme-client.sh $(TARGET_DIR)/usr/sbin/acme-client
	$(INSTALL) -D -m 0755 $(@D)/acme.sh $(TARGET_DIR)/stat/etc/acme/acme.sh
	cp -a $(@D)/dnsapi $(TARGET_DIR)/stat/etc/acme/
	cp -a $(@D)/notify $(TARGET_DIR)/stat/etc/acme/
	ln -sf /mnt/kd/acme $(TARGET_DIR)/etc/acme
	# Remove non-required dnsapi files
	rm -f $(addprefix $(TARGET_DIR)/stat/etc/acme/dnsapi/, $(ACME_REMOVE_TARGET_DNSAPI))
	# Make the scripts non-executable, they are sourced by acme.sh
	find $(TARGET_DIR)/stat/etc/acme/dnsapi/ -name '*.sh' -print0 | xargs -0 chmod 644
	find $(TARGET_DIR)/stat/etc/acme/notify/ -name '*.sh' -print0 | xargs -0 chmod 644
endef

define ACME_UNINSTALL_TARGET_CMDS
	rm -f  $(TARGET_DIR)/usr/sbin/acme-client
	rm -f  $(TARGET_DIR)/etc/acme
	rm -rf $(TARGET_DIR)/stat/etc/acme
endef

$(eval $(call GENTARGETS,package,acme))
