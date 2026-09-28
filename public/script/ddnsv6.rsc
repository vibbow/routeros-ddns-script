##########################################
## RouterOS DDNS 脚本 for 阿里云 / 腾讯云 IPv6版
##
## 该 DDNS 脚本可自动 获取/识别/更新 IP 地址
## 兼容 阿里云 / 腾讯云 DNS接口
## 适用于 RouterOS v7
##
## 作者: vibbow
## https://vsean.net/
##
## 修改日期: 2026/09/28
##
## 该脚本无任何售后技术支持
## Use it wisely
##########################################

# 域名
:local domainName "sub.example.com";

# wan接口名称
:local wanInterface "ether1";

# 要使用的服务 (alidns/aliesa/dnspod)
:local service "alidns";

# API接口 Access ID
:local accessID "";

# API接口 Access Secret
:local accessSecret "";

# ==== 以下内容无需修改 ====
# =========================

:local publicIP;
:local dnsIP;
:local epicFail false;

# 获取接口上的公网IPv6地址
:do {
  :local addressIDs [ /ipv6 address find interface=$wanInterface global disabled=no invalid=no deprecated=no ];

  :foreach id in=$addressIDs \
  do={
    :local eachAddress [ /ipv6 address get $id address ];
    :set eachAddress [ :toip6 [ :pick $eachAddress 0 [ :find $eachAddress "/" ] ] ];

    # 排除 ULA (fc00::/7)，取第一个公网地址
    :if ([ :typeof $publicIP ] != "ip6" && !($eachAddress in fc00::/7)) \
    do={
      :set publicIP $eachAddress;
    }
  }

  :if ([ :typeof $publicIP ] != "ip6") \
  do={
    :set epicFail true;
    :log error ("DDNSv6: No public IP on interface " . $wanInterface);
  }
} \
on-error {
  :set epicFail true;
  :log error ("DDNSv6: Get public IP failed.");
}

# 获取当前解析的IP
:if ($epicFail = false) \
do={
  :do {
    :set dnsIP [ :resolve domain-name=$domainName type=ipv6 ];
  } \
  on-error {
    :log warning ("DDNSv6: Resolve domain " . $domainName . " failed.");
  }
}

# 如IP有变动，则更新解析
:if ($epicFail = false && $publicIP != $dnsIP) \
do={
  :local postData ("service=" . $service . "&domain=" . $domainName . "&ip=" . $publicIP . "&access_id=" . $accessID . "&access_secret=" . $accessSecret);

  :do {
    :local fetchResult [ /tool fetch url="https://ddns6.vsean.net/ddns.php" http-method=post http-data=$postData as-value output=user ];
    :log info ("DDNSv6: " . ($fetchResult->"data"));
  } \
  on-error {
    :log error ("DDNSv6: Update request failed.");
  }
}
