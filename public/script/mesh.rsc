##########################################
## RouterOS WireGuard Mesh 同步脚本
##
## 定时向服务器登记本机的 WireGuard 公钥、监听端口与公网IP
## 并自动更新同一 Mesh 内其他节点的 endpoint 地址与端口
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

# Mesh 网络 ID
# 8~32 位，只允许小写字母、数字、_ 和 -
# 知道 ID 即可加入该 Mesh，建议使用足够长的随机字符串
:local meshID "test1234";

# WireGuard 接口名称
:local wgInterface "wg-mesh";

# 如要使用 IPv6 地址组网，改为 ddns6.vsean.net
:local meshServer "ddns.vsean.net";

# 说明:
# 1. 系统名称 (System -> Identity) 用于区分节点，同一 Mesh 内不能重复
#    只允许字母、数字、_ 和 -，不能是默认的 MikroTik
# 2. 其他节点需要先在本机的 WireGuard peers 中手动添加 (公钥、Allowed Address 等)
#    脚本只会更新已存在 peer 的 endpoint 地址与端口

# ==== 以下内容无需修改 ====
# =========================

:local response;
:local epicFail false;

# 登记本机信息，获取其他节点列表
:do {
  :local wgIDs [ /interface wireguard find name=$wgInterface ];

  :if ([ :len $wgIDs ] = 0) \
  do={
    :set epicFail true;
    :log error ("Mesh: WireGuard interface " . $wgInterface . " not found.");
  } \
  else={
    :local identityName [ /system identity get name ];
    :local wgPublicKey [ /interface wireguard get [ :pick $wgIDs 0 ] public-key ];
    :local wgListenPort [ /interface wireguard get [ :pick $wgIDs 0 ] listen-port ];

    :local url ("https://" . $meshServer . "/mesh/" . $meshID);
    :local postData ("identity_name=" . [ :convert $identityName to=url ] . "&wg_listen_port=" . $wgListenPort . "&wg_public_key=" . [ :convert $wgPublicKey to=url ]);

    :local fetchResult [ /tool fetch url=$url check-certificate=yes-without-crl http-method=post http-data=$postData as-value output=user ];
    :set response [ :deserialize from=json ($fetchResult->"data") ];

    :if ([ :typeof ($response->"error") ] = "str") \
    do={
      :set epicFail true;
      :log error ("Mesh: " . ($response->"error"));
    }
  }
} \
on-error {
  :set epicFail true;
  :log error ("Mesh: Request mesh server failed.");
}

# 更新其他节点的 endpoint
:if ($epicFail = false) \
do={
  :foreach peer in=($response->"peers") \
  do={
    :local peerIDs [ /interface wireguard peers find interface=$wgInterface public-key=($peer->"public_key") ];

    :if ([ :len $peerIDs ] = 1) \
    do={
      :local endpointAddress ($peer->"endpoint_address");
      :local endpointPort ($peer->"endpoint_port");
      :local currentAddress [ /interface wireguard peers get $peerIDs endpoint-address ];
      :local currentPort [ /interface wireguard peers get $peerIDs endpoint-port ];

      :if ($currentAddress != $endpointAddress || $currentPort != $endpointPort) \
      do={
        /interface wireguard peers set $peerIDs endpoint-address=$endpointAddress endpoint-port=$endpointPort;
        :log info ("Mesh: Update " . ($peer->"name") . " to " . $endpointAddress . " port " . $endpointPort);
      }
    }
  }
}
