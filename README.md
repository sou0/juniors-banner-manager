# Junior's Banner Manager (funnel-cta)

**Junior's Banner Manager** é um plugin robusto para WordPress projetado para gerenciar CTAs (Call-to-Action) dinâmicos, banners de funil de vendas, banners de override (substituição por URL) e banners independentes via shortcodes. O plugin suporta testes A/B via randomização de banners, segmentação avançada por tipo de dispositivo (Desktop vs. Mobile), temporização/agendamento, cronômetros de contagem regressiva e controle inteligente de injeção de posicionamento de texto.

A injeção do banner no conteúdo dos posts é feita via JavaScript no front-end, garantindo alta compatibilidade com plugins de cache (como WP Rocket, LiteSpeed Cache e Cloudflare).

---

## 🛠️ Tecnologias e Bibliotecas Utilizadas

O projeto é estruturado utilizando as seguintes tecnologias e dependências:

1. **PHP (WordPress Plugin API):** Código principal do lado do servidor estruturado na classe `FunnelCTAManager`.
2. **JavaScript (Vanilla):** Executado no cliente para:
   - Injeção dinâmica dos banners no corpo dos artigos (evitando problemas de cache).
   - Atualização em tempo real dos cronômetros de contagem regressiva.
3. **CSS (Vanilla):** Estilização do painel administrativo integrado ao padrão do WordPress e formatação básica dos banners.
4. **Python (Scripts de Manutenção):** Scripts utilitários no diretório raiz para refatorações automatizadas e aplicação de patches no arquivo PHP principal:
   - `check_php.py`: Analisador básico de sintaxe.
   - `fix_form.py`, `fix_list.py`, `fix_quality.py`: Aplicação de correções de layout e lógica.
   - `patch.py`, `patch2.py`: Aplicação de patches e modificações em lote no código PHP.
5. **plugin-update-checker (v5.7):** Biblioteca integrada para permitir atualizações automáticas do plugin diretamente a partir do repositório GitHub (`https://github.com/sou0/juniors-banner-manager/`) através da branch `main`.

---

## ✨ Funcionalidades Principais

### 1. Banners de Funil (Funnel CTAs)
Segmenta a exibição automática de banners com base no estágio do funil configurado para o post atual:
*   **Topo de Funil**
*   **Meio de Funil**
*   **Fundo de Funil**
*   **Banner Global:** Exibido em todos os posts, opcionalmente de forma simultânea com outros banners.
*   **Imagem Padrão (Fallback):** Exibida quando um post possui um estágio definido, mas não há banner ativo configurado para aquele estágio.

### 2. Banners de Override (Substituição Personalizada)
Substitui ou complementa os banners de funil em URLs específicas.
*   Suporta **Wildcards (`*`)** nas URLs de destino para correspondência de padrões (ex: `/categoria/*`).
*   Agendamento de exibição com data/hora de início e fim baseadas no fuso horário configurado no WordPress.
*   Opção de exibição exclusiva (oculta os outros banners) ou simultânea.

### 3. Banners via Shortcode
Banners criados no painel que podem ser inseridos de forma independente em qualquer página ou post usando o shortcode:
```text
[fcm_banner id="scb_xxxxxx"]
```

### 4. Rotação / Randomização de Banners (Testes A/B)
Para cada banner configurado, é possível cadastrar variações de banners alternativos. O plugin selecionará um banner aleatoriamente a cada carregamento de página, ideal para testar conversões de diferentes criativos.

### 5. Configuração por Tipo de Dispositivo
Banners podem ter layouts e mídias completamente separados para **Desktop** e **Mobile**:
*   Desativação individual da exibição em Desktop ou Mobile.
*   Seleção de tipos independentes (Imagem ou HTML/Shortcode) para cada dispositivo.
*   Destinos de links diferentes por dispositivo.

### 6. Posicionamento Inteligente no Texto
Permite configurar onde os banners serão injetados dentro do conteúdo dos artigos:
*   **No Início:** Antes do primeiro parágrafo.
*   **No Fim:** Ao final do texto do artigo.
*   **Após "X" Parágrafos:** Injeção exata após o parágrafo especificado.
*   **No Meio (Inteligente):** Identifica a metade do texto e injeta o banner de forma segura, evitando quebrar elementos visuais adjacentes como cabeçalhos (`h1`-`h6`), listas (`ul`, `ol`), imagens, tabelas, blockquotes e iframes de mídia.

### 7. Cronômetros de Contagem Regressiva (Countdown)
Shortcodes dinâmicos que exibem o tempo restante para a expiração de um banner agendado (Override ou Shortcode). O cronômetro se atualiza a cada segundo via JS.

Unidades disponíveis:
*   `[fcm_ano id="banner_id"]` - Exibe os anos restantes.
*   `[fcm_mes id="banner_id"]` - Exibe os meses restantes.
*   `[fcm_dia id="banner_id"]` - Exibe os dias restantes.
*   `[fcm_hora id="banner_id"]` - Exibe as horas restantes.
*   `[fcm_minuto id="banner_id"]` - Exibe os minutos restantes.
*   `[fcm_segundo id="banner_id"]` - Exibe os segundos restantes.

### 8. Painel Administrativo Completo
*   **Visão Geral (Dashboard):** Acompanhamento rápido do status de todos os banners padrões.
*   **Lista de Funil:** Gerenciamento centralizado de posts classificados, permitindo alteração em lote de estágios, posições, e importação de classificações via CSV.
*   **Monitor de Conflitos:** Ferramenta analítica que alerta caso banners de Override estejam conflitando com os estágios de funil dos mesmos posts.

---

## ⚙️ Instalação e Requisitos

### Requisitos
*   WordPress 5.0 ou superior.
*   PHP 7.4 ou superior.

### Instalação Manual
1. Faça o download ou clone este repositório para o diretório `/wp-content/plugins/junior-banner-manager/`.
2. Ative o plugin através do painel administrativo do WordPress na seção **Plugins**.
3. Acesse o menu **Junior's Banner** criado na barra lateral para começar a configurar os seus CTAs.
