import sys

with open('juniors-banner-manager.php', 'r') as f:
    content = f.read()

# 1. Update mainContainer selection logic and 'top' positioning
old_js_start = "            var mainContainer = null;"
old_js_end = "                if (b.pos === 'top') {"

# Search for the block
start_idx = content.find(old_js_start)
end_idx = content.find(old_js_end)

if start_idx != -1 and end_idx != -1:
    new_js_logic = """            var mainContainer = null;
            var maxP = 0;

            // Prioridade para seletores mais específicos
            for (var i = 0; i < selectors.length; i++) {
                var els = document.querySelectorAll(selectors[i]);
                els.forEach(function(el) {
                    // Ignorar se o elemento estiver oculto
                    if (el.offsetWidth === 0 && el.offsetHeight === 0) return;
                    
                    var pList = el.querySelectorAll('p');
                    if (pList.length > maxP) {
                        maxP = pList.length;
                        mainContainer = el;
                    }
                    // Fallback: Se ainda não temos container, pegamos o primeiro que aparecer mesmo sem <p>
                    if (!mainContainer && els.length > 0) {
                        mainContainer = els[0];
                    }
                });
            }

            if (!mainContainer) return;

            var usedPositions = {};

            banners.forEach(function(b) {
                var posKey = b.pos === 'after_p' ? 'after_p_' + b.pCount : b.pos;
                if (usedPositions[posKey]) return;
                usedPositions[posKey] = true;

                var div = document.createElement('div');
                div.innerHTML = b.html;
                var bannerWrapper = div.querySelector('.fcm-banner-wrapper');
                if (!bannerWrapper) return;

                var deviceContainer = isMobile ? bannerWrapper.querySelector('.fcm-mobile-container') : bannerWrapper.querySelector('.fcm-desktop-container');
                var items = deviceContainer.querySelectorAll('.fcm-random-item');
                if (items.length === 0) return;

                var randomItem = items[Math.floor(Math.random() * items.length)];
                randomItem.style.display = 'block';
                randomItem.style.margin = '40px 0';
                randomItem.style.textAlign = 'center';

                if (b.pos === 'top') {
                    // Tentar inserir antes do primeiro parágrafo ou título para evitar ficar acima do cabeçalho do post
                    var firstContent = mainContainer.querySelector('p, h1, h2, h3, h4, h5, h6, ul, ol, img');
                    if (firstContent) {
                        mainContainer.insertBefore(randomItem, firstContent);
                    } else {
                        mainContainer.insertBefore(randomItem, mainContainer.firstChild);
                    }
                    return;
                }"""
    content = content[:start_idx] + new_js_logic + content[end_idx + len(old_js_end):]

with open('juniors-banner-manager.php', 'w') as f:
    f.write(content)
