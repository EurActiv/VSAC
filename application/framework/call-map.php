<?php

namespace VSAC;

use_module('backend-all');
use_module('callmap');


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

$get_node_ids = function ($edges) {
    $nodes = array();
    foreach ($edges as $edge) {
        unset($edge['id']);
        $edge = array_values(array_map('intval', $edge));
        $nodes = array_merge($nodes, $edge);
    }
    return array_unique($nodes);
};

$filter_edges = function ($nids, $edges) {
    return array_filter($edges, function ($edge) use ($nids) {
        unset($edge['id']);
        $edge = array_map('intval', $edge);
        extract($edge);
        return in_array($cid, $nids) || in_array($pid, $nids) || in_array($gid, $nids); 
    });
};

backend_head('API Call Map', [], function () {

    $s = '<script src="%s"></script>';
    printf($s, router_url('cdn/springy/2.0.1/springy.js'));
    printf($s, router_url('cdn/springy/2.0.1/springyui.js'));

});

// top menu
$base_url = router_url(basename(__FILE__));
$options = array(
    'default',
    'show_gateways',
    'gateways_as_consumers',
    'gateways_as_providers',
);
$options = array_map(function ($option) use ($base_url) {
    return sprintf(
        '<a href="%s" class="btn btn-link %s">%s</a>',
        router_add_query($base_url, array('type' => $option)),
        request_query('type', 'default') == $option ? 'disabled' : '',
        ucfirst(str_replace('_', ' ', $option))
    );
}, $options);
echo '<p class="pull-right">Click any node for details</p>';
echo '<p class="pull-left">' . implode('|' , $options) . '</p>';
echo '<hr style="clear:both">';

$data = callmap_dump();
if ($base = request_query('base')) {
    $base_id = 0;
    foreach ($data['nodes'] as $node) {
        if ($node['label'] == $base) {
            $base_id = (int) $node['id'];
        }
    }
    $edges = $filter_edges(array($base_id), $data['edges']);
    $data['edges'] = array_values($edges);
}
$colors = $get_colors(count($data['edges']));
?>
<script>(function (global, $){
    var data = <?= json_encode($data); ?>;
    var colors = <?= json_encode($colors); ?>;
    var query = <?= json_encode(request_query_all()) ?>;
    var nodes = {};
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
        return n.node;
    }
    var addEdge = function (cid, pid, color) {
        consumer = getNode(cid);
        provider = getNode(pid);
        if (consumer && provider) {
            graph.newEdge(provider, consumer, {color: color});
        }
    };
    if (query.base) {
        query.type = 'show_gateways';
    }
    data.nodes.map(function (node) {
        nodes['_' + node.id] = {label: node.label};
    });
    data.edges.map(function (edge) {
        var consumer, provider, gateway;
        var color = colors.shift();
        switch (query.type) {
            case 'show_gateways':
                addEdge(edge.cid, edge.gid, color);
                addEdge(edge.gid, edge.pid, color);
                break;
            case 'gateways_as_consumers':
                addEdge(edge.gid, edge.pid, color);
                break;
            case 'gateways_as_providers':
                addEdge(edge.cid, edge.gid, color);
                break;
            default:
                addEdge(edge.cid, edge.pid, color);
                break;
        }
    });

    $(function(){
        var canvas = $('#callmap');
        canvas.attr('width', canvas.parent().width());
        var springy = window.springy = canvas.springy({
            graph: graph,
            nodeSelected: function(node){
                canvas.data('rebase', node.data.label);
            }
        });
        canvas.on('mousemove', function () {
            canvas.data('rebase', false);
        });
        canvas.on('click', function () {
            var href = global.location.href, rebase = canvas.data('rebase');
            if (rebase) {
                href = href.replace(/\?.*$/, '');
                href += '?type=none&base=' + encodeURIComponent(rebase);
                global.location.href = href;
            }
        });
    });
}(window, jQuery));</script>
<?php
?>
<canvas id="callmap" width="640" height="480" />

<?php

backend_foot();


