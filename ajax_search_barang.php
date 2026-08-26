<?php
require_once __DIR__ . '/config/auth.php';
require_login();

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$q = sanitize($_GET['q'] ?? '');

if (empty($q) || strlen(trim($q)) === 0) {
    echo json_encode([]);
    exit;
}

// Fungsi normalisasi string teks
function normalizeSearchText($str) {
    // Normalisasi unicode simbol perkalian seperti × menjadi x
    $str = str_replace(['×', '✕', '✖', 'X'], 'x', $str);
    // Ubah ke lowercase
    $str = mb_strtolower(trim($str), 'UTF-8');
    return $str;
}

$q_raw = trim($q);
$q_clean = normalizeSearchText($q_raw);

// Tokenize query kata per kata (pisahkan berdasarkan spasi atau pemisah umum)
$tokens = array_values(array_filter(preg_split('/[\s,\-_:\/\\\\]+/', $q_clean)));
if (empty($tokens)) {
    echo json_encode([]);
    exit;
}

// Ambil semua daftar barang dan jenisnya
$query = "SELECT j.id as jenis_id, j.nama_jenis, j.satuan, j.harga, j.stok, b.id as barang_id, b.nama_barang 
          FROM jenis_barang j 
          JOIN stok_barang b ON j.barang_id = b.id";
$res = mysqli_query($conn, $query);

if (!$res) {
    echo json_encode([]);
    exit;
}

$scored_results = [];
$total_tokens = count($tokens);

while ($row = mysqli_fetch_assoc($res)) {
    $nama_b = $row['nama_barang'] ?? '';
    $nama_j = $row['nama_jenis'] ?? '';
    
    $combined_raw = trim($nama_b . ' ' . $nama_j);
    $combined = normalizeSearchText($combined_raw);
    $combined_words = array_values(array_filter(preg_split('/[\s,\-_:\/\\\\]+/', $combined)));

    $score = 0;

    // 1. Exact full combined match (Super High Priority)
    if ($combined === $q_clean) {
        $score += 1000;
    } 
    // 2. Exact substring match in combined string
    elseif (strpos($combined, $q_clean) !== false) {
        $score += 500;
        // Bonus jika diawali kata yang dicari
        if (strpos($combined, $q_clean) === 0 || strpos(normalizeSearchText($nama_b), $q_clean) === 0) {
            $score += 200;
        }
    }

    // 3. Multi-token matching (kata per kata, tidak terpengaruh urutan atau kata terlewat)
    $matched_tokens_count = 0;
    $total_token_score = 0;

    foreach ($tokens as $t) {
        $t_len = strlen($t);
        $token_matched = false;
        $best_token_score = 0;

        // Cek substring langsung di gabungan teks
        if (strpos($combined, $t) !== false) {
            $token_matched = true;
            $best_token_score = 100;
            // Bonus jika cocok persis dengan salah satu kata atau awal kata
            foreach ($combined_words as $cw) {
                if ($cw === $t) {
                    $best_token_score = 150;
                    break;
                } elseif (strpos($cw, $t) === 0) {
                    $best_token_score = 120;
                }
            }
        } else {
            // Fuzzy / typo / prefix / phonetic check terhadap setiap kata
            foreach ($combined_words as $cw) {
                $cw_len = strlen($cw);
                // Jika salah satu adalah prefix dari yang lain (cth: 'plasti' vs 'plastik')
                if (($cw_len >= 3 && strpos($t, $cw) === 0) || ($t_len >= 3 && strpos($cw, $t) === 0)) {
                    $token_matched = true;
                    $best_token_score = max($best_token_score, 90);
                } else {
                    // Levenshtein distance check untuk typo (toleransi 1-2 karakter beda, cth: fremium vs premium)
                    if ($t_len >= 3 && $cw_len >= 3) {
                        $lev = levenshtein($t, $cw);
                        $max_allowed_lev = ($t_len <= 4) ? 1 : 2;
                        if ($lev <= $max_allowed_lev) {
                            $token_matched = true;
                            $best_token_score = max($best_token_score, 85 - ($lev * 15));
                        }
                    }
                }
            }
        }

        if ($token_matched) {
            $matched_tokens_count++;
            $total_token_score += $best_token_score;
        }
    }

    $score += $total_token_score;

    // Bonus jika semua token kata yang diketik ditemukan
    if ($matched_tokens_count === $total_tokens) {
        $score += 300;
    }

    // Rasio token yang cocok (jika multi kata)
    if ($total_tokens > 1 && $matched_tokens_count > 0) {
        $match_ratio = $matched_tokens_count / $total_tokens;
        $score += ($match_ratio * 150);
    }

    // Subsequence matching (bonus jika urutan kata relatif searah meskipun ada kata terlewat)
    $subseq_index = 0;
    $in_order_count = 0;
    foreach ($combined_words as $cw) {
        if ($subseq_index < $total_tokens) {
            $curr_token = $tokens[$subseq_index];
            if ($cw === $curr_token || strpos($cw, $curr_token) !== false || (strlen($curr_token) >= 3 && levenshtein($curr_token, $cw) <= 1)) {
                $in_order_count++;
                $subseq_index++;
            }
        }
    }
    if ($in_order_count >= 2) {
        $score += ($in_order_count * 50);
    }

    // Global string similarity bonus (similar_text)
    similar_text($q_clean, $combined, $sim_percent);
    $score += ($sim_percent * 0.5);

    // Kriteria kelayakan hasil:
    // 1. Semua token cocok, ATAU
    // 2. Jika query multi-token, minimal 50% token cocok & skor memadai, ATAU
    // 3. Minimal 1 token cocok dengan skor memadai
    $pass = false;
    if ($matched_tokens_count === $total_tokens) {
        $pass = true;
    } elseif ($total_tokens > 1 && ($matched_tokens_count / $total_tokens) >= 0.5 && $score >= 100) {
        $pass = true;
    } elseif ($matched_tokens_count > 0 && $score >= 80) {
        $pass = true;
    }

    if ($pass) {
        $scored_results[] = [
            'barang_id' => (int)$row['barang_id'],
            'nama_barang' => $row['nama_barang'],
            'jenis_id' => (int)$row['jenis_id'],
            'nama_jenis' => $row['nama_jenis'],
            'satuan' => $row['satuan'],
            'harga' => (float)$row['harga'],
            'stok' => (float)$row['stok'],
            'score' => $score
        ];
    }
}

// Urutkan berdasarkan skor relevansi tertinggi, lalu nama barang & jenis
usort($scored_results, function($a, $b) {
    if (abs($a['score'] - $b['score']) > 0.01) {
        return ($b['score'] > $a['score']) ? 1 : -1;
    }
    $c = strcmp($a['nama_barang'], $b['nama_barang']);
    if ($c !== 0) return $c;
    return strcmp($a['nama_jenis'], $b['nama_jenis']);
});

// Ambil batas maksimal 15 rekomendasi teratas
$final_results = array_slice($scored_results, 0, 15);

// Hapus field skor sebelum dikirim ke frontend
$output = array_map(function($item) {
    unset($item['score']);
    return $item;
}, $final_results);

echo json_encode($output);
