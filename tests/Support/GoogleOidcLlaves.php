<?php

namespace Tests\Support;

/**
 * Pares RSA estáticos SOLO para tests OIDC (nunca en producción).
 * Evitan generar llaves en cada ejecución (lento y frágil en Windows).
 */
class GoogleOidcLlaves
{
    public const KID_A = 'test-kid-a';

    public const PRIVADA_A = '-----BEGIN PRIVATE KEY-----
MIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQDKe/rUFAz1aiOS
PUZKXgxhcPau5ez08fQ2gDgPRiK2yZovNWDstrILOuTCX4NmiJPliORwz5dBHk6t
sMfOyJXgz4tHvR1JT9lVHpTuO4FlBNZlVGId6wr6UKq/8MHm9zKgggQ8wlPcqkWc
km2Z7jsF9ljNvNosR7l4d70bcyn1uZfu4JsQLPAl8BEHno6VaQ8X3Tp7PJodbqN7
CH9OnsA8vt9uFd0+rKsyfvXSMIjitMeromkOenWP92fAFkTDywqSgd+LVLeLyNmR
qnG6Lomo65TIp5mlswuNCr/ZeKtyWhePUa38c9Q1aJrjOFYAS3mkybvBZ8nCYePE
hj1FddaRAgMBAAECggEACFmFdeJfyGz4i4rAT8fCDfCZE4xCsbUag3bVGPgk7VGx
7HlwhkJgZCspDMTgh8VhB+mVtfsxewtzZiEwlBvcZllYnnwzbXb/+M0aihplExPY
fXQ12ZL8MGdMKz8T1+DgsxK5dwHycFqLJVI6d8sFnxDSB6Xef03z3X5ei3e7xriu
OeNPPb/xQwFxTNDEVl91Ag9VgzLD6XtRlxPzTUhktAZifNshdo4eIx0ykyOlQ/Yg
zKvjwQfMiQU5TLHXVz7CdB/Rdar7alKHkWOAuvAMDzZHfmOTi0g/10P0nYONk8fh
XWAEAIitLKJ8aVRw229wyqyonyqnSTFGw6GY9zyPGQKBgQDuJsUMZVQgc/iM2QIt
f32y0e+QIECAkKDT/oqO4i6CyVlt7yk+mxyD2yQdbg224gVnXsmG3Y/wtqv13LzU
IH+TaWMkbTn7FOONGNe/+66uJP5dkZ78Nz21ZtP/iii5/9aswDpAd14/cf60npf6
JEW0LIODAMnguQCmWbplmt6f9QKBgQDZqORFlG0zQh9F08LfDzaVxVnugizr0h9Q
/PAk4VxNIsZxVLZPklCrFa1hzuuCO3kq5KEkyrNVjLsM6q1phnDdJTXiPb+yEB88
F4gV4FaOhmehh7wI0WudhlV0PzFWcTDxiLh5vCa0/Qo4gjaH61cStXkDxOaeK2GH
N+S/GbQGrQKBgQC5LzqQfgahyawfUevIl1eWAnsndnzRvCTjVA5INwb1uj4pJwzK
WvEccv4tSFOvvyd2jxVkIst34YPsBf5rcO3jIdLvE2Q/oqmjIQs7hSj6z1dgSQj4
lLgOY+w7zCIJKDC1WjCxymBmM21c4+nHoAb3qJmG98kDTrh7olA4C+pLNQKBgQCG
TFxUxkk+qFzNY+TIZwWiEbv0myOGegbSnM67osWl3oAc60z1+eyGM4DgaaEMoQ+Z
NKptHhja/1Nmm+JVyxsWiGAf8UO3ZZUwKcDQ5rPhivKkSzDtK/I/puRRLrCNipfN
YDObAUnMwJiu10E4SjBWndxGRBabn1605PriIsTenQKBgQDk0xmCvlVfbEbL1LPl
S1CgezofKDC26PWg7F9B50vmfzgNUWBcbkJWsIlml1et5Ofv/RKbxS26huDpWx/A
0Adpf5LUbP5wMpv5nGYkUUYA8RQMr18ArXNIe7m9nQti9M+S/tPh0qU/Y6sUWk1n
7v1J0Ul9oU5sI2ii5xmM6Q9KCg==
-----END PRIVATE KEY-----
';

    public const N_A = 'ynv61BQM9Wojkj1GSl4MYXD2ruXs9PH0NoA4D0YitsmaLzVg7LayCzrkwl-DZoiT5YjkcM-XQR5OrbDHzsiV4M-LR70dSU_ZVR6U7juBZQTWZVRiHesK-lCqv_DB5vcyoIIEPMJT3KpFnJJtme47BfZYzbzaLEe5eHe9G3Mp9bmX7uCbECzwJfARB56OlWkPF906ezyaHW6jewh_Tp7APL7fbhXdPqyrMn710jCI4rTHq6JpDnp1j_dnwBZEw8sKkoHfi1S3i8jZkapxui6JqOuUyKeZpbMLjQq_2XircloXj1Gt_HPUNWia4zhWAEt5pMm7wWfJwmHjxIY9RXXWkQ';

    public const E_A = 'AQAB';

    public const PRIVADA_B = '-----BEGIN PRIVATE KEY-----
MIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQCw0sn+z4WUVqMr
vByEJ3zlAUWDJQDvIDBGLJA+1tn7h6gcV+HsbbC47bcdefRVDVOP/una+B3Zcj+K
x1RfOEl3wO74o6J2nWRQVGAWPC+94NhUX6babmF0vh7n3FbH7jHhVPFdItoN5Hs8
fVQFS1HWF9deSPiAQ+YFUUD0UJheSSJDkEOy/HJ9uOOhtvqWtZfID1ci9VGTeknU
sysSaOP6r8OF4lwn4UtlfVhWigNMLubJ0aBBtXQKpssawKdd9Wi9M97QRC+uuqQS
bXuxQgKGlUdPho/6vtOCRvyep7LuSnwpZ0LuwYe8RrDxtfRQryhkAqAm7ec5fzRo
XH5jHh6FAgMBAAECggEADQMaA8MUNqgQfmOrqor4XSbUjXPP54j0HHnjjIhFhsXD
GIDcGoiw6E7v+n6SvR6Lr21IGsnG8o0+gVEtU4uCFoqvW0M5baaj7uhioMhBkCTH
bqASWIEmcU+bWR8lELXl3EnisCKkLsEJU5h6ciBKj6l1+xV+GfxFJU+HKuNybv+t
uQFb4w18z1/WJlE0hkd0xyXgUfmhXfyc4nOAvQAfRh6wXUPkLDE3aEpLP9imtXMO
YBK+XgKLSsfCB3r7mti9LL18L+J6mK4UH29umYMnoT0ZitmBj+6JOiNFjIidroan
Fx+yOfq01C9znm4EFZHGhrDBTCjdUp8+JzO/tvQHaQKBgQDvwG2xKFXBwnJVWkaM
MNMKTTxSONTVpoAA9hQ/YVHoE/6qizKzH5WJteD4UiaB9D5d27m1/DCQRQQL0U3A
5iGB2tYXonuHNd5H/TmVrMnetwtvp4m+IYUCh7FlGqYkpxrlam7VJQF4SJuRZyJx
VrdVBj1kmWKCt+ZFWtcwULNWHQKBgQC8zpYftDUhFh4DaLdDkzsoBZJhbj//GQX4
LpTFoch5i8qQe2HwSkuyvjzJN7y++rbHKVpWOfO1WRuBSb15wJ/XQLXslPPhg2bH
5CAwp0HsL8o+ACCTMFdO3cLSJDl+FKJinkiM6UVmDBPqvpo5yRLunMASMYgunZ7/
PuRWx6bdiQKBgQC+7bCtV6DLuC1TGZx7Alrepll2ZTnhl0HzAgVn3/nZZyr05/uN
OIbe70/kkY+HCFouZtBDRD3sdnnda2aPSvW+MucyVTwollaZX+je8Iam7YCW/4Yr
Owhw1l0IViufntBFiPgQl/LOtovlRIviVJuVg22a0qfnz8n6y3f5Sxs2ZQKBgQCz
F661MgX3dXhQuYKk2cKSSv635pFT05Oi/+RktvpnuN8TP8X93SuCyAr2vyUNbtnz
ftlO0zfF/FknwR+2nq2/y4UFfaD8d90FnPGf1PemJwrK1uoPVBptF9OV64CRhNcn
fobJCvimPKyjzdqTF5Uau5tBZrSLqz3HVgvX+RfiKQKBgQDLwv+iSrliuCnKnBHK
NgkAkYm0hVzRuYXIFD8VA205tTNjycJKBac5alUnYKj5cXUGcdC7hDCxTmpywHsB
ffcxY83qYbtVqGodfQymY1my6Mr2R+5oUVFlByjNxwzkvChG2o1+2ASGV5ndYYfH
tBqx5LVkLznmurI7ajd1k6USGw==
-----END PRIVATE KEY-----
';

    /**
     * JWKS con la llave pública A (la que firma los tokens del test).
     */
    public static function jwks(string $kid = self::KID_A): array
    {
        return ['keys' => [[
            'kty' => 'RSA',
            'kid' => $kid,
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => self::N_A,
            'e' => self::E_A,
        ]]];
    }
}
