<?php

namespace VSAC;

use_module('backend-all');
use_module('callmap');

//----------------------------------------------------------------------------//
//-- functions                                                              --//
//----------------------------------------------------------------------------//

/**
 * Generate a pseudo rainbow so that we can see what goes where
 */
$get_colors = function ($steps) {
    $randhex = function () {
        return str_pad(dechex(rand(0, 255)), 2, '0', STR_PAD_LEFT);
    };
    $randcolor = function () use ($randhex) {
        return '#' . $randhex() . $randhex() . $randhex();
    };
    $return = array();
    for ($i = 0; $i < $steps; $i += 1) {
        $return[] = $randcolor();
    }
    return $return;
};

/**
 * Generate the form for selecting nodes
 */
$form = function ($all_nodes, $selected_nodes) {
    form_form(['id'=>'select-nodes'], function () use ($all_nodes, $selected_nodes) {
        $gateway_regex = '/^\[.*\]$/';
        $link = router_url(basename(__FILE__)) . '?base=';
        $tr = function ($label, $id) use ($link, $selected_nodes) {
            echo "<td>";
            $checked = in_array($id, $selected_nodes);
            form_checkbox($checked, $label, "nodes[{$id}]", 'node__'.$id);
            echo "</td>";
            echo "<td><a href='{$link}{$id}'><i class='fa fa-eye'></i></a></td>";
            echo "</tr>";
        };
        echo "<table class='table-hover'>";
        echo '<tr><th colspan="2">Show Gateways</th></tr>';
        foreach ($all_nodes as $node) {
            if (preg_match($gateway_regex, $node['label'])) {
                $tr($node['label'], $node['id']);
            }
        }
        echo '<tr><th colspan="2">Show Nodes</th></tr>';
        foreach ($all_nodes as $node) {
            if (!preg_match($gateway_regex, $node['label'])) {
                $tr($node['label'], $node['id']);
            }
        }
        echo "</table>";
        form_submit();
    });
};

/**
 * Generate the visualisation
 */
$visualize = function ($visualize_nodes, $visualize_edges) use ($get_colors) {
    $colors = $get_colors(count($visualize_edges));
    $data = array(
        'nodes' => array_values($visualize_nodes),
        'edges' => array_values($visualize_edges),
    );
    ?>
    <script>(function (global, $){
        var data = <?= json_encode($data); ?>;
        var colors = <?= json_encode($colors); ?>;
        var nodes = {};
        var edges = [];
        var graph = new Springy.Graph();
        var getNode = function (node_id) {
            var n = nodes['_' + node_id];
            if (!n) {
                return false;
            }
            if (!n.node) {
                n.node = graph.newNode({
                    label: n.label
                });
            }
            return n;
        }
        var addEdge = function (from, to, color, always) {
            var ename = from.id + '_' + to.id;
            if (always || edges.indexOf(ename) < 0) {
                graph.newEdge(from.node, to.node, {color: color});
            }
            edges.push(ename);
        };
        data.nodes.map(function (node) {
            nodes['_' + node.id] = node;
        });
        data.edges.map(function (edge) {
            var consumer = getNode(edge.cid),
                provider = getNode(edge.pid),
                gateway = getNode(edge.gid),
                always = consumer && provider && gateway;
                color = colors.shift();
            if (!gateway && consumer && provider) {
                addEdge(provider, consumer, color, always);
            }
            if (gateway && consumer) {
                addEdge(gateway, consumer, color, always);
            }
            if (gateway && provider) {
                addEdge(provider, gateway, color, always);
            }
        });

        $(function(){
            var canvas = $('#callmap');
            canvas.attr('width', canvas.parent().width());
            canvas.attr('height', Math.min(1500, Math.max(400, $('#select-nodes').height())));
            var springy = window.springy = canvas.springy({
                graph: graph,
            });
        });
    }(window, jQuery));</script>
    <canvas id="callmap" width="640" height="480" />
    <?php
};

/**
 * Generate the callmap table
 */
$table = function ($visualize_nodes, $visualize_edges) {
    // and for the tables
    $table_nodes = array();
    foreach ($visualize_nodes as $node) {
        $table_nodes[$node['id']] = $node['label'];
    }

    echo "<table class='table table-bordered'>";
    echo "<tr><th>Consumer</th><th>Via</th><th>Provider</th></tr>";
    $link = router_url(basename(__FILE__)) . '?base=';
    $td = function ($id) use ($table_nodes, $link) {
        printf(
            '<td><a href="%s">%s</a></td>',
            $link . $id,
            isset($table_nodes[$id]) ? $table_nodes[$id] : '<i class="fa fa-eye"></i>'
        );
    };
    foreach ($visualize_edges as $edge) {
        echo "<tr>";
        $td($edge['cid']);
        $td($edge['gid']);
        $td($edge['pid']);
        echo "</tr>";
    }
    echo "</table>";
};

//----------------------------------------------------------------------------//
//-- data processing                                                        --//
//----------------------------------------------------------------------------//


// fetch and normalize all data
$data = callmap_dump();
$all_nodes = array_map(function ($node) {
    $node['id'] = (int) $node['id'];
    return $node;
}, $data['nodes']);
$all_edges = array_map(function ($edge) {
    unset($edge['id']);
    $edge = array_map('intval', $edge);
    return $edge;
}, $data['edges']);

if ($base = (int) request_query('base', '')) {
    $new_nodes = array();
    foreach ($all_edges as $edge) {
        if (in_array($base, $edge)) {
            foreach ($edge as $node_id) {
                if (!isset($new_nodes[$node_id])) {
                    $new_nodes[$node_id] = 1;
                }
            }
        }
    }
    response_redirect(router_add_query(
        router_url(basename(__FILE__)),
        array('nodes'=>$new_nodes)
    ));
}

// get and normalize the nodes that are selected
$selected_nodes = array_keys(request_query('nodes', array()));
if (empty($selected_nodes)) {
    $selected_nodes = config('callmap_visualize_default', array());
    $selected_nodes = array_map(__NAMESPACE__ . '\\callmap_get_node_id', $selected_nodes);
}
$selected_nodes = array_map('intval', $selected_nodes);

// get the nodes and edges that will be visualized
$visualize_nodes = array_filter($all_nodes, function ($node) use ($selected_nodes) {
    return in_array($node['id'], $selected_nodes);
});
$visualize_edges = array_filter($all_edges, function ($edge) use ($selected_nodes) {
    return in_array($edge['cid'], $selected_nodes)
        || in_array($edge['pid'], $selected_nodes)
        || in_array($edge['gid'], $selected_nodes);
});




//----------------------------------------------------------------------------//
//-- output                                                                 --//
//----------------------------------------------------------------------------//


backend_head('API Call Map', [], function () {
    $s = '<script src="%s"></script>';
    printf($s, router_url('cdn/springy/2.0.1/springy.js'));
    printf($s, router_url('cdn/springy/2.0.1/springyui.js'));
    ?><style>
        form table a { visibility:hidden; display:block;  margin:3px; }
        form table tr:hover a { visibility:visible;}
        form table .checkbox {margin-top:3px; margin-bottom:3px;}
    </style><?php
});


echo '<div class="row"><div class="col-sm-3">';

$form($all_nodes, $selected_nodes);


echo '</div><div class="col-sm-9">';

if (count($visualize_nodes) > 50) {
    $table($visualize_nodes, $visualize_edges);
} else {
    $visualize($visualize_nodes, $visualize_edges);
}

echo '</div><div class="col-sm-3">';


echo '</div>';


backend_foot();


