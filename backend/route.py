import heapq
import math
from urllib.parse import urlparse

from flask import Flask, jsonify, request
import pymysql

app = Flask(__name__)

FLOOR_WEIGHT = 10.0
BUILDING_WEIGHT = 25.0
ESCALATOR_PENALTY = 8.0
STAIR_PENALTY = 40.0


@app.after_request
def allow_local_frontend(response):
    origin = request.headers.get('Origin')
    if origin:
        parsed_origin = urlparse(origin)
        if (
            parsed_origin.scheme in ('http', 'https')
            and parsed_origin.hostname in ('localhost', '127.0.0.1')
        ):
            response.headers['Access-Control-Allow-Origin'] = origin
            response.headers['Access-Control-Allow-Methods'] = 'GET, OPTIONS'
            response.headers['Access-Control-Allow-Headers'] = 'Accept, Content-Type'
            response.headers['Vary'] = 'Origin'
    return response


def get_db():
    return pymysql.connect(
        host='127.0.0.1',
        user='root',
        password='',
        database='mapnev',
        cursorclass=pymysql.cursors.DictCursor,
    )


def edge_kind(name):
    normalized = str(name or '').casefold()
    if 'stair' in normalized:
        return 'stair'
    # The current database spells "Esculator" this way.
    if 'escal' in normalized or 'escul' in normalized:
        return 'escalator'
    if 'lift' in normalized or 'elevator' in normalized:
        return 'lift'
    if any(word in normalized for word in ('hallway', 'corridor', 'walkway', 'ramp', 'accessible')):
        return 'passage'
    return 'other'


def load_graph():
    conn = get_db()
    try:
        with conn.cursor() as cursor:
            cursor.execute(
                'SELECT n.*, nt.name AS node_type_name '
                'FROM node AS n LEFT JOIN nodetype AS nt ON nt.id = n.type'
            )
            node_rows = cursor.fetchall()
            cursor.execute(
                'SELECT e.*, et.name AS edge_type_name '
                'FROM edge AS e LEFT JOIN edgetype AS et ON et.id = e.edgetype '
                'WHERE e.enable = 1'
            )
            edge_rows = cursor.fetchall()
    finally:
        conn.close()
    return node_rows, edge_rows


def hierarchy_ids(nodes):
    """Resolve each node's containing floor and building from its parent chain."""
    hierarchy = {}
    for node_id, node in nodes.items():
        floor_id = None
        building_id = None
        current = node
        seen = set()

        while current:
            current_id = int(current['id'])
            if current_id in seen:
                break
            seen.add(current_id)

            node_type = str(current.get('node_type_name') or '').casefold()
            if floor_id is None and node_type == 'floor':
                floor_id = current_id
            if building_id is None and node_type == 'building':
                building_id = current_id

            parent_id = current.get('parant')
            if parent_id is None or int(parent_id) == current_id:
                break
            current = nodes.get(int(parent_id))

        hierarchy[node_id] = (floor_id, building_id)
    return hierarchy


def coordinates(node):
    try:
        x = float(node['posx'])
        y = float(node['posy'])
    except (KeyError, TypeError, ValueError):
        return None
    if not math.isfinite(x) or not math.isfinite(y):
        return None
    return x, y


def astar(start_id, end_id, step_free_only=True):
    node_rows, edge_rows = load_graph()
    nodes = {int(node['id']): node for node in node_rows}
    if start_id not in nodes or end_id not in nodes:
        return None

    hierarchy = hierarchy_ids(nodes)
    adjacency = {node_id: [] for node_id in nodes}
    allowed_edges = []

    for edge in edge_rows:
        kind = edge_kind(edge.get('edge_type_name'))
        if step_free_only and kind not in ('lift', 'passage'):
            continue

        first_id = int(edge['node1'])
        second_id = int(edge['node2'])
        if first_id not in nodes or second_id not in nodes:
            continue

        base_cost = float(edge['weight'])
        if not math.isfinite(base_cost) or base_cost < 0:
            raise ValueError('Edge weights must be finite and non-negative.')

        type_penalty = {
            'escalator': ESCALATOR_PENALTY,
            'stair': STAIR_PENALTY,
        }.get(kind, 0.0)
        first_floor, first_building = hierarchy[first_id]
        second_floor, second_building = hierarchy[second_id]
        floor_cost = FLOOR_WEIGHT if first_floor != second_floor else 0.0
        building_cost = BUILDING_WEIGHT if first_building != second_building else 0.0
        cost = base_cost + type_penalty + floor_cost + building_cost

        adjacency[first_id].append((second_id, cost, edge))
        adjacency[second_id].append((first_id, cost, edge))
        allowed_edges.append((first_id, second_id, base_cost))

    # Scale the straight-line heuristic so its spatial term never exceeds any
    # edge's base cost; floor/building terms are charged separately above.
    distance_scale = math.inf
    for first_id, second_id, base_cost in allowed_edges:
        first_pos = coordinates(nodes[first_id])
        second_pos = coordinates(nodes[second_id])
        if first_pos is None or second_pos is None:
            continue
        distance = math.hypot(
            first_pos[0] - second_pos[0],
            first_pos[1] - second_pos[1],
        )
        if distance > 0:
            distance_scale = min(distance_scale, base_cost / distance)
    if not math.isfinite(distance_scale):
        distance_scale = 0.0

    goal_pos = coordinates(nodes[end_id])
    goal_floor, goal_building = hierarchy[end_id]

    def heuristic(node_id):
        node_pos = coordinates(nodes[node_id])
        spatial_cost = 0.0
        if node_pos is not None and goal_pos is not None:
            spatial_cost = distance_scale * math.hypot(
                node_pos[0] - goal_pos[0],
                node_pos[1] - goal_pos[1],
            )

        floor_id, building_id = hierarchy[node_id]
        floor_cost = FLOOR_WEIGHT if floor_id != goal_floor else 0.0
        building_cost = BUILDING_WEIGHT if building_id != goal_building else 0.0
        return spatial_cost + floor_cost + building_cost

    scores = {start_id: 0.0}
    previous = {}
    queue = [(heuristic(start_id), 0.0, start_id)]

    while queue:
        _, current_cost, current_id = heapq.heappop(queue)
        if current_cost > scores.get(current_id, math.inf):
            continue
        if current_id == end_id:
            break

        for neighbor_id, edge_cost, edge in adjacency[current_id]:
            candidate_cost = current_cost + edge_cost
            if candidate_cost < scores.get(neighbor_id, math.inf):
                scores[neighbor_id] = candidate_cost
                previous[neighbor_id] = (current_id, edge)
                heapq.heappush(
                    queue,
                    (candidate_cost + heuristic(neighbor_id), candidate_cost, neighbor_id),
                )

    if end_id not in scores:
        return None

    path = [end_id]
    path_edges = []
    current_id = end_id
    while current_id in previous:
        parent_id, edge = previous[current_id]
        path_edges.insert(0, edge)
        path.insert(0, parent_id)
        current_id = parent_id

    return {
        'path': path,
        'edges': path_edges,
        'total_weight': scores[end_id],
        'nodes': {str(node_id): node for node_id, node in nodes.items()},
    }


@app.route('/api/route', methods=['GET', 'OPTIONS'])
def route():
    try:
        start_id = int(request.args.get('from', '18'))
        end_id = int(request.args.get('to', '47'))
    except (TypeError, ValueError):
        return jsonify({'ok': False, 'error': '"from" and "to" must be integer node IDs.'}), 400

    step_free = request.args.get('step_free', '1') == '1'
    try:
        result = astar(start_id, end_id, step_free)
    except (pymysql.MySQLError, ValueError) as error:
        app.logger.exception('Route calculation failed')
        return jsonify({'ok': False, 'error': 'Route calculation failed.'}), 500

    if result is None:
        return jsonify({'ok': False, 'error': 'No accessible route was found.'}), 404
    return jsonify({'ok': True, 'data': result})


if __name__ == '__main__':
    app.run(host='127.0.0.1', port=5000)
