# Update for #NAME#

:local wgPeer$ID$ [ find public-key="$PK$" ]
:local wgPeer$ID$Count [ :len $wgPeer$ID$ ]

:if ($wgPeer$ID$Count = 1) \
do={
    :local wgPeer$ID$EndpointAddress [ get [ find where public-key="$PK$"] endpoint-address ]
    :local wgPeer$ID$EndpointPort [ get [ find where public-key="$PK$"] endpoint-port ]

    :if ($wgPeer$ID$EndpointAddress != "$EA$") \
    do={
        set endpoint-address="$EA$" [ find public-key="$PK$"]
    }

    :if ($wgPeer$ID$EndpointPort != "$EP$") \
    do={
        set endpoint-port="$EP$" [ find public-key="$PK$"]
    }
}

