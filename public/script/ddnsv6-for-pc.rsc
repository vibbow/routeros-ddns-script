##########################################
## RouterOS DDNS 脚本 for 阿里云 / 腾讯云 IPv6版
##
## 该 DDNS 脚本可自动对指定 PC 做 IPv6 DDNS
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

:local addressList [ :toarray "" ];
:local dnsIP;
:local dnsIPFound false;
:local epicFail false;

# 获取指定MAC的所有公网IPv6地址
:do {
  :local neighborIDs [ /ipv6 neighbor find mac-address=$macAddress interface=$lanInterface status!="failed" ];

  :foreach id in=$neighborIDs \
  do={
    :local eachAddress [ /ipv6 neighbor get $id address ];

    # 排除 ULA (fc00::/7) 与链路本地地址 (fe80::/10)
    :if (!(($eachAddress in fc00::/7) || ($eachAddress in fe80::/10))) \
    do={
      :set addressList ($addressList, $eachAddress);
    }
  }

  :if ([ :len $addressList ] = 0) \
  do={
    :set epicFail true;
    :log error ("DDNSv6: No public IP found for " . $macAddress);
  }
} \
on-error {
  :set epicFail true;
  :log error ("DDNSv6: Get IP of " . $macAddress . " failed.");
}

# 获取当前解析的IP
# 计算机通常同时有多个IPv6地址（稳定地址 + 临时地址）
# 当前解析的IP仍属于这台计算机时不更新，避免解析在多个地址间来回切换
:if ($epicFail = false) \
do={
  :do {
    :set dnsIP [ :resolve domain-name=$domainName type=ipv6 ];
  } \
  on-error {
    :log warning ("DDNSv6: Resolve domain " . $domainName . " failed.");
  }

  :foreach address in=$addressList \
  do={
    :if ($address = $dnsIP) \
    do={
      :set dnsIPFound true;
    }
  }
}

# 更新 IPv6 地址到 DDNS
:if ($epicFail = false && $dnsIPFound = false) \
do={
  :local publicIP ($addressList->0);
  :local postData ("service=" . $service . "&domain=" . $domainName . "&ip=" . $publicIP . "&access_id=" . $accessID . "&access_secret=" . $accessSecret);

  :do {
    :local fetchResult [ /tool fetch url="https://ddns6.vsean.net/ddns.php" http-method=post http-data=$postData as-value output=user ];
    :log info ("DDNSv6: " . ($fetchResult->"data"));
  } \
  on-error {
    :log error ("DDNSv6: Update request failed.");
  }
}
