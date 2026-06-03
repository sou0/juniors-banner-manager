import sys

with open('/home/basilica/produtos/plugin-classificacao/plugin classificação.php', 'r') as f:
    content = f.read()

old_list_logic = """                                    $colors = ['topo' => '#d1ecf1', 'meio' => '#fff3cd', 'fundo' => '#f8d7da'];
                                    $bg = isset($colors[$stage]) ? $colors[$stage] : '#eee';

                                    echo '<tr>';
                                    echo '<td><strong>' . get_the_title() . '</strong><br><small><a href="' . get_permalink() . '" target="_blank">' . get_permalink() . '</a></small></td>';
                                    echo '<td><span style="background:'. $bg .'; padding: 5px 10px; border-radius: 4px; font-weight: bold; text-transform: uppercase; font-size: 10px;">' . esc_html($stage) . '</span></td>';"""

new_list_logic = """                                    $colors = ['topo' => '#d1ecf1', 'meio' => '#fff3cd', 'fundo' => '#f8d7da'];
                                    $bg = isset($colors[$stage]) ? $colors[$stage] : '#eee';
                                    $stage_label = $stage;
                                    if ($stage === 'topo') $stage_label = $label_topo;
                                    if ($stage === 'meio') $stage_label = $label_meio;
                                    if ($stage === 'fundo') $stage_label = $label_fundo;

                                    echo '<tr>';
                                    echo '<td><strong>' . get_the_title() . '</strong><br><small><a href="' . get_permalink() . '" target="_blank">' . get_permalink() . '</a></small></td>';
                                    echo '<td><span style="background:'. $bg .'; padding: 5px 10px; border-radius: 4px; font-weight: bold; text-transform: uppercase; font-size: 10px;">' . esc_html($stage_label) . '</span></td>';"""

content = content.replace(old_list_logic, new_list_logic)

with open('/home/basilica/produtos/plugin-classificacao/plugin classificação.php', 'w') as f:
    f.write(content)
