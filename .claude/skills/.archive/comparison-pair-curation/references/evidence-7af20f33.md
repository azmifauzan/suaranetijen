user: "tambahkan, myrepublic itu bukannya product sekaligus brand?"

assistant: "Ya. MyRepublic adalah merek perusahaan sekaligus satu-satunya layanannya (internet rumah). Di model kita satu entitas hanya punya satu tipe. ... Tipenya tidak saya ubah, karena tipe juga menentukan JSON-LD (`Organization` vs `Service`). Pasangannya cukup ditambahkan."

assistant (commit): "feat(seo): add 27 demand-backed comparison pairs ... Each pair has both sides above the public threshold on production, the same category and type, and a Google Autocomplete suggestion for \"X vs Y\". A test guards that every shipped pair is in canonical order, since a non-canonical entry silently never matches a page."