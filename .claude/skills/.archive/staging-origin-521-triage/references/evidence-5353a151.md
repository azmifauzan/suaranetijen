Connection to 103.194.172.114 22 port [tcp/ssh] succeeded!
nc: connect to 103.194.172.114 port 443 (tcp) failed: Connection refused
nc: connect to 103.194.172.114 port 80 (tcp) failed: Connection refused

nginx-proxy	Exited (131) 9 minutes ago
suaranetijen-app	Up 8 minutes
suaranetijen-ssr	Up 8 minutes (healthy)

restart=unless-stopped labels=/home/dev/compose/nginx
nginx-proxy
Up 8 seconds
/ 200
/up 200