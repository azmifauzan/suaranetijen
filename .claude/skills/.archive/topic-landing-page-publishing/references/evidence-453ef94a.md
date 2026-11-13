smartphone-kamera-bagus => published
smartphone-desain-model-keren => published
smartphone-performa-lancar => published
sleep 20; for s in smartphone-kamera-bagus smartphone-desain-model-keren smartphone-performa-lancar; do curl -s -o /dev/null -w "$s %{http_code}\n" -A 'Mozilla/5.0' https://suaranetijen.id/topik/$s; echo -n "  in sitemap: "; curl -s https://suaranetijen.id/sitemap.xml | grep -c "/topik/$s<"; done
smartphone-kamera-bagus 200
  in sitemap: 1
homepage topik links: href="/topik/smartphone-awet-tahan-lama" href="/topik/smartphone-desain-model-keren" href="/topik/smartphone-harga-bersaing" href="/topik/smartphone-kamera-bagus" href="/topik/smartphone-performa-lancar"
name="robots" content="index, follow