##########################################
## RouterOS DDNS 脚本 for 阿里云 / 腾讯云 IPv6版
##
## 该 DDNS 脚本可自动 获取/识别/更新 IP 地址
## 兼容 阿里云 / 腾讯云 DNS接口
##
## 作者: vibbow
## https://vsean.net/
##
## 修改日期: 2024/7/22
##
## 该脚本无任何售后技术支持
## Use it wisely
##########################################

# 域名
:local domainName "sub.example.com";
# wan接口名称
:local wanInterface "ether1";
# 要使用的服务 (aliyun/dnspod)
:local service "aliyun";
# API接口 Access ID
:local accessID "";
# API接口 Access Secret
:local accessSecret "";


# 腾讯云 (dnspod) 设置
#
# 一般情况下无需设置此内容
# 服务器会自动识别 domainID 和 recordID
#
# 如一直提示 "当前域名无权限，请返回域名列表。"
# 则需要手动设置
:local domainID "";
:local recordID "";


# ==== 以下内容无需修改 ====
# =========================

:local publicIP;
:local dnsIP;
:local epicFail false;

# 获取当前接口IPv6地址
:do {
  :local interfaceIP;
  :local interfaceIPList [ /ipv6 address find interface=$wanInterface global ];
  :local interfaceIPListSize [ :len $interfaceIPList ];

  # 如果接口上只有一个IP，那么直接使用这个IP
  if ($interfaceIPListSize = 1) \
  do={
    :set $interfaceIP [ /ipv6 address get $interfaceIPList address ];
  }

  # 如果接口上有多个IP，那么找到非内网地址的IP
  if ($interfaceIPListSize > 1) \
  do={
    :foreach id in $interfaceIPList \
    do={
      :local eachAddress [ /ipv6 address get $id address ];
      :local isLinkLocal false;

      if ($eachAddress in fc00::/7) \
      do={
        :set $isLinkLocal true;
      }

      if ($eachAddress in fd00::/8) \
      do={
        :set $isLinkLocal true;
      }

      if ($eachAddress in fe80::/10) \
      do={
        :set $isLinkLocal true;
      }

      if (!$isLinkLocal) \
      do={
        :set interfaceIP $eachAddress;
      }
    }
  }

  :local interfaceIPLength [ :len $interfaceIP ];

  if ($interfaceIPLength = 0) \
  do={
    set $epicFail true;
    :log error ("DDNSv6: No public IP on interface . " $wanInterface);
  } \
  else={
    :set $interfaceIP [ :pick $interfaceIP 0 [ :find $interfaceIP "/" ] ];
    :set $publicIP [ :toip6 $interfaceIP ];
#    :log info ("DDNSv6: Current interface IP is " . $publicIP);
  }
} \
on-error {
  :set $epicFail true;
  :log error ("DDNSv6: Get public IP failed.");
}

# 获取当前解析的IP
:do {
  :set $dnsIP [ :resolve $domainName ];
#  :log info ("DDNSv6: Current resolved IP is " . $dnsIP);
} \
on-error {
  :set $epicFail true;
  :log error ("DDNSv6: Resolve domain " . $domainName . " failed.");
}

# 如IP有变动，则更新解析
:if ($epicFail = false && $publicIP != $dnsIP) \
do={
    :local callUrl ("https://ddns6.vsean.net/ddns.php");
    :local postData ("service=" . $service . "&domain=" . $domainName . "&access_id=" . $accessID . "&access_secret=" . $accessSecret . "&domain_id=" . $domainID . "&record_id=" . $recordID);
    :local fetchResult [/tool fetch url=$callUrl mode=https http-method=post http-data=$postData as-value output=user];
    :log info ("DDNSv6: " . $fetchResult->"data");
}
