# RouterOS DDNS

为 MikroTik RouterOS 路由器提供的脚本与服务端，包含两个独立的功能：

- **DDNS**：路由器公网 IP 变化时，自动更新阿里云 DNS / 阿里云 ESA / 腾讯云 DNSPod 的域名解析，支持 IPv4 与 IPv6
- **WireGuard Mesh**：多台路由器组成 WireGuard 网状网络时，自动同步各节点的 endpoint 地址与端口

脚本仅支持 RouterOS v7，并在最新的 stable 与 long-term 版本上测试通过。

可以直接使用 <https://ddns.vsean.net/> 提供的免费服务，功能说明与脚本下载都在这个页面上。

## 脚本

| 脚本 | 说明 |
| --- | --- |
| [`ddns.rsc`](public/script/ddns.rsc) | IPv4 DDNS。WAN 接口上是内网或运营商级 NAT 地址时，会通过外部服务获取公网 IP |
| [`ddnsv6.rsc`](public/script/ddnsv6.rsc) | IPv6 DDNS，使用 WAN 接口上的公网 IPv6 地址 |
| [`ddnsv6-for-pc.rsc`](public/script/ddnsv6-for-pc.rsc) | 按 MAC 地址为局域网内的设备做 IPv6 DDNS |
| [`mesh.rsc`](public/script/mesh.rsc) | WireGuard Mesh 节点同步 |

使用方法：修改脚本开头的变量，将脚本添加到 `/system script`，再在 `/system scheduler` 中设置定时执行。

- DDNS 脚本只在当前 IP 与域名解析不一致时才提交更新。要更新的记录需要事先在 DNS 服务商处创建好，脚本不会自动创建
- Mesh 脚本只更新已存在的 WireGuard peer，peer 需要事先手动添加。知道 Mesh ID 即可加入该 Mesh，请使用足够长的随机字符串
- 运行结果会写入路由器日志，以 `DDNS:`、`DDNSv6:` 或 `Mesh:` 开头

脚本中 `service` 变量的取值：

| 取值 | 服务商 |
| --- | --- |
| `alidns`（或 `aliyun`） | 阿里云 云解析 DNS |
| `aliesa` | 阿里云 边缘安全加速 ESA（站点需使用 NS 接入） |
| `dnspod` | 腾讯云 DNSPod |

建议为 DDNS 单独创建 RAM 子账号（阿里云）或子用户（腾讯云），只授予 DNS 解析的管理权限。

## 自行部署

服务端为纯 PHP 实现，不依赖 Composer 或云厂商 SDK。

- PHP 8.5 或以上，需要 curl 扩展
- 网站根目录指向 `public/`，其余目录不应能被直接访问
- `cache/`、`logs/`、`mesh/nodes/` 需要可被 PHP 写入
- 将脚本里的 `ddns.vsean.net` 替换为自己的域名

Caddy 配置示例：

```caddyfile
ddns.example.com {
	root * /var/www/routeros-ddns-script/public

	# 可选：返回访问者的 IP
	handle /ip {
		respond "{client_ip}"
	}

	rewrite /ddns /ddns.php
	rewrite /mesh/* /mesh.php

	php_fastcgi unix//run/php/php-fpm.sock
	file_server
}
```

## 目录结构

```
public/          网站根目录
  ddns.php       DDNS 接口
  mesh.php       Mesh 接口（/mesh/<Mesh ID>）
  index.html     首页
  script/        RouterOS 脚本
src/             服务端代码
cache/           缓存
logs/            错误日志
mesh/nodes/      Mesh 节点数据
```

更新记录见 [CHANGELOG.md](CHANGELOG.md)。

---

该脚本无任何售后技术支持  
Use it wisely
