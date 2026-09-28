# Mesh 网络 ID
# 限制 8~32 位，只允许小写字母、数字
:local meshID "test1234"

# WireGuard 接口名称
:local wgMeshInterface "wg-mesh"

# 以下内容无需修改
:local identityName [ /system/identity get name ]
:local wgPublicKey [ /interface wireguard get [find name=$wgMeshInterface] public-key ]
:local wgListenPort [ /interface wireguard get [find name=$wgMeshInterface] listen-port ]

:set $identityName [ :convert $identityName to=url]
:set $wgPublicKey [ :convert $wgPublicKey to=url]

# 如果要使用 ipv6 地址，则使用 ddns6.vsean.net
:local url ("https://ddns.vsean.net/mesh/" . $meshID)
:local postData ("identity_name=" . $identityName . "&wg_listen_port=" . $wgListenPort . "&wg_public_key=" . $wgPublicKey)

/tool fetch url=$url mode=https http-method=post http-data=$postData output=file dst-path="wg_mesh.rsc";
import file=wg_mesh.rsc

