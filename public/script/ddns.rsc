##########################################
## RouterOS DDNS 脚本 for 阿里云 / 腾讯云
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

# 获取当前外网IP
:do {
  :local addressIDs [ /ip address find interface=$wanInterface disabled=no invalid=no ];

  :if ([ :len $addressIDs ] = 0) \
  do={
    :set epicFail true;
    :log error ("DDNS: No IP address on interface " . $wanInterface);
  } \
  else={
    :local interfaceIP [ /ip address get [ :pick $addressIDs 0 ] address ];
    :set interfaceIP [ :toip [ :pick $interfaceIP 0 [ :find $interfaceIP "/" ] ] ];

    # 接口上是内网地址（如光猫拨号、运营商NAT），通过外部服务获取公网IP
    :if (($interfaceIP in 10.0.0.0/8) || ($interfaceIP in 172.16.0.0/12) || ($interfaceIP in 192.168.0.0/16) || ($interfaceIP in 100.64.0.0/10)) \
    do={
      :local fetchResult [ /tool fetch url="http://ip.3322.net/" as-value output=user ];
      :local fetchData ($fetchResult->"data");
      :local lineEnd [ :find $fetchData "\n" ];

      :if ([ :typeof $lineEnd ] = "num") \
      do={
        :set fetchData [ :pick $fetchData 0 $lineEnd ];
      }

      :set publicIP [ :toip $fetchData ];
    } \
    else={
      :set publicIP $interfaceIP;
    }

    :if ([ :typeof $publicIP ] != "ip") \
    do={
      :set epicFail true;
      :log error ("DDNS: Get public IP failed.");
    }
  }
} \
on-error {
  :set epicFail true;
  :log error ("DDNS: Get public IP failed.");
}

# 获取当前解析的IP
:if ($epicFail = false) \
do={
  :do {
    :set dnsIP [ :resolve domain-name=$domainName type=ipv4 ];
  } \
  on-error {
    :log warning ("DDNS: Resolve domain " . $domainName . " failed.");
  }
}

# 如IP有变动，则更新解析
:if ($epicFail = false && $publicIP != $dnsIP) \
do={
  :local postData ("service=" . $service . "&domain=" . $domainName . "&ip=" . $publicIP . "&access_id=" . $accessID . "&access_secret=" . $accessSecret);

  :do {
    :local fetchResult [ /tool fetch url="https://ddns.vsean.net/ddns.php" check-certificate=yes-without-crl http-method=post http-data=$postData as-value output=user ];
    :log info ("DDNS: " . ($fetchResult->"data"));
  } \
  on-error {
    :log error ("DDNS: Update request failed.");
  }
}
