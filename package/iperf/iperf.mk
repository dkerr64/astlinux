#############################################################
#
# iperf
#
#############################################################

IPERF_VERSION = 2.2.0
IPERF_SOURCE = iperf-$(IPERF_VERSION).tar.gz
IPERF_SITE = https://downloads.sourceforge.net/project/iperf2

IPERF_CONF_OPT = \
	--disable-dependency-tracking \
	--disable-web100

$(eval $(call AUTOTARGETS,package,iperf))
