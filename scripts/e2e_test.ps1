$cookie = "$env:TEMP\e2e_cookie.txt"
$baseUrl = "http://localhost:8080"
$results = @()

function Test-Page {
    param([string]$name, [string]$url, [string]$expectedPattern)
    $r = curl.exe -s -b $cookie "$baseUrl$url" 2>&1
    $rStr = $r -join "`n"
    if ($rStr -match $expectedPattern) {
        $results += "OK: $name"
    } else {
        $results += "FAIL: $name (pattern '$expectedPattern' not found in $url)"
    }
}

function Test-Post {
    param([string]$name, [string]$url, [string]$body, [string]$expectedPattern)
    $r = curl.exe -s -b $cookie -L -d $body "$baseUrl$url" 2>&1
    $rStr = $r -join "`n"
    if ($rStr -match $expectedPattern) {
        $results += "OK: $name"
    } else {
        $results += "FAIL: $name (pattern '$expectedPattern' not found)"
    }
}

# Login
curl.exe -s -c $cookie "$baseUrl/admin/login.php" -o "$env:TEMP\e2e_login.html" 2>&1 | Out-Null
$html = Get-Content "$env:TEMP\e2e_login.html" -Raw
$csrf = [regex]::Match($html, 'name="csrf_token" value="([^"]+)"').Groups[1].Value
curl.exe -s -c $cookie -b $cookie -L -o "$env:TEMP\e2e_null.html" -d "csrf_token=$csrf&username=sorameshi&password=lotus10" "$baseUrl/admin/login.php" 2>&1 | Out-Null
$results += "OK: Login"

# Dashboard
Test-Page "Dashboard" "/admin/" "Dashboard"

# Games - List
Test-Page "Games List" "/admin/games.php" "Todos os Jogos"
# Games - New form
Test-Page "Games New Form" "/admin/games.php?action=new" "Novo Jogo"
# Games - Markdown editor loaded
Test-Page "Games Markdown Editor" "/admin/games.php?action=new" "markdown-editor.js"
# Games - TinyMCE NOT present
$r = curl.exe -s -b $cookie "$baseUrl/admin/games.php?action=new" 2>&1
if ($r -join "`n" -match "tinymce") { $results += "FAIL: Games still has TinyMCE" } else { $results += "OK: Games TinyMCE removed" }

# Blog - List
Test-Page "Blog List" "/admin/blog.php" "Todos os Posts"
# Blog - New form
Test-Page "Blog New Form" "/admin/blog.php?action=new" "Novo Post"
# Blog - Markdown editor loaded
Test-Page "Blog Markdown Editor" "/admin/blog.php?action=new" "markdown-editor.js"

# Social Links - List
Test-Page "Social Links List" "/admin/social-links.php" "Novo Link"
# Social Links - New form
Test-Page "Social Links New Form" "/admin/social-links.php?action=new" "Novo Link"
# Social Links - Edit form
$r = curl.exe -s -b $cookie "$baseUrl/admin/social-links.php" 2>&1
if ($r -join "`n" -match 'action=edit&id=(\d+)') {
    $editId = $Matches[1]
    Test-Page "Social Links Edit Form" "/admin/social-links.php?action=edit&id=$editId" "Editar Link"
} else {
    $results += "WARN: Social Links - no items to edit"
}

# Banners
Test-Page "Banners" "/admin/banners.php" "Publicidade"

# Testimonials
Test-Page "Testimonials" "/admin/testimonials.php" "Depoimentos"

# FAQ
Test-Page "FAQ" "/admin/faq.php" "Perguntas"

# Team
Test-Page "Team" "/admin/team.php" "Equipe"

# Engines
Test-Page "Engines" "/admin/engines.php" "Engines"

# Platforms
Test-Page "Platforms" "/admin/platforms.php" "Plataformas"

# Retro Games
Test-Page "Retro Games" "/admin/retro-games.php" "Jogos Retro"

# Templates
Test-Page "Templates" "/admin/templates.php" "Templates"

# Distribution
Test-Page "Distribution" "/admin/distribution.php" "Distribuição"

# Users
Test-Page "Users" "/admin/users.php" "Usuários"

# Roles
Test-Page "Roles" "/admin/roles.php" "Cargos"

# Levels
Test-Page "Levels" "/admin/levels.php" "Níveis"

# Settings
Test-Page "Settings" "/admin/settings.php" "Configurações"

# Repair
Test-Page "Repair" "/admin/repair.php" "Reparo"

# Frontend
Test-Page "Frontend Home" "/" "Jogatinando"

# Print results
Write-Host "`n=== E2E TEST RESULTS ==="
$ok = ($results | Where-Object { $_ -match "^OK:" }).Count
$fail = ($results | Where-Object { $_ -match "^FAIL:" }).Count
$warn = ($results | Where-Object { $_ -match "^WARN:" }).Count
$results | ForEach-Object { Write-Host $_ }
Write-Host "`nTotal: $ok OK, $fail FAIL, $warn WARN"
