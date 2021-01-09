#############################################################
#
# tcpdump
#
#############################################################

TCPDUMP_VERSION = 4.99.0
TCPDUMP_SITE = https://www.tcpdump.org/release
TCPDUMP_SOURCE = tcpdump-$(TCPDUMP_VERSION).tar.gz

## Should be fixed in 4.99.1
TCPDUMP_AUTORECONF = YES

TCPDUMP_CONF_ENV = \
	td_cv_buggygetaddrinfo=no \
	PCAP_CONFIG=$(STAGING_DIR)/usr/bin/pcap-config

TCPDUMP_CONF_OPT = \
	--without-crypto \
	--disable-local-libpcap \
	$(if $(BR2_PACKAGE_TCPDUMP_SMB),--enable-smb,--disable-smb)

TCPDUMP_DEPENDENCIES = zlib libpcap

# make install installs an unneeded extra copy of the tcpdump binary
define TCPDUMP_REMOVE_DUPLICATED_BINARY
	rm -f $(TARGET_DIR)/usr/bin/tcpdump.$(TCPDUMP_VERSION)
endef

TCPDUMP_POST_INSTALL_TARGET_HOOKS += TCPDUMP_REMOVE_DUPLICATED_BINARY

$(eval $(call AUTOTARGETS,package,tcpdump))
