##########################################
## RouterOS DDNS 脚本 for 阿里云 / 腾讯云 IPv6版
##
## 该 DDNS 脚本可自动对指定 PC 做 IPv6 DDNS
## 兼容 阿里云 / 腾讯云 DNS接口
##
## 作者: vibbow
## https://vsean.net/
##
## 修改日期: 2025/12/17
##
## 该脚本无任何售后技术支持
## Use it wisely
##########################################

# 用来DDNS的域名
:local domainName "sub.example.com";

# 要更新的计算机MAC地址
:local macAddress "AA:BB:CC:DD:EE:FF";

# 用来查找计算机的端口 (通常是bridge)
:local lanInterface "bridge";

# 要使用DDNS的服务 (alidns/aliesa/dnspod)
:local service "alidns";

# DDNS API接口 Access ID
:local accessID "";

# DDNS API接口 Access Secret
:local accessSecret "";

# ==== 以下内容无需修改 ====
# =========================

:local epicFail false;
:local ipv6Address;
:local ipv6AddressList;
:local dnsAddress;

# 获取指定mac的所有ipv6地址
:do {
  :set ipv6AddressList [ /ipv6 neighbor find mac-address=$macAddress interface=$lanInterface ];

  :local addressListLength [ :len $ipv6AddressList ];

  if ($addressListLength = 0) \
  do={
    :log error ("No ipv6 address found for " . $macAddress);
    :set epicFail true;
  }
} \
on-error {
  :set epicFail true;
}


# 获取非本地的ipv6地址
if ($epicFail = false) \
do={
  :foreach id in=$ipv6AddressList \
  do={
    :local eachAddress [ /ipv6 neighbor get $id address ];
    :local eachAddressStr [ :toip6 $eachAddress ];
    :local isLinkLocal false;

    if ($eachAddress in fc00::/7) \
    do={
      :set isLinkLocal true;
    }

    if ($eachAddress in fe80::/10) \
    do={
      :set isLinkLocal true;
    }

    if (!$isLinkLocal) \
    do={
      :set ipv6Address $eachAddressStr;
    }
  }

  :local addressLength [ :len $ipv6Address ];
  if ($addressLength = 0) \
  do={
    :log error ("No public ipv6 address for " . $macAddress);
    :set epicFail true;
  }
}


# 获取当前解析的IP
:do {
  :set dnsAddress [ :resolve $domainName ];
} \
on-error {
  :set epicFail true;
  :log error ("Resolve domain " . $domainName . " failed.");
}


# 更新 IPv6 地址到 DDNS
if ($epicFail = false && $ipv6Address != $dnsAddress) \
do={
    :local callUrl ("https://ddns6.vsean.net/ddns.php");
    :local postData ("service=" . $service . "&domain=" . $domainName . "&ip=" . $ipv6Address . "&access_id=" . $accessID . "&access_secret=" . $accessSecret);
    :local fetchResult [/tool fetch url=$callUrl mode=https http-method=post http-data=$postData as-value output=user];
    :log info ("DDNSv6: " . $fetchResult->"data");
}
