import heapq
import math
from urllib.parse import urlparse

from flask import Flask, jsonify, request
import pymysql

app = Flask(__name__)

WALK_SPEED_MPS = 1.25  # metres per second (75 m/min); converts distance into seconds

FLOOR_WEIGHT = 10.0     # seconds
BUILDING_WEIGHT = 25.0  # seconds
ESCALATOR_PENALTY = 8.0 # seconds
STAIR_PENALTY = 40.0    # seconds
LIFT_PENALTY = 70.0     # seconds of waiting for a lift


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
        database='MapNav',
        cursorclass=pymysql.cursors.DictCursor,
    )


def edge_kind(edgetype):
    """Identify the edge kind from the edge.edgetype integer."""
    if edgetype is None:
        return 'other'
    return {
        1: 'passage',    # Standard Hallway
        3: 'stair',      # Stairs
        5: 'escalator',  # Esculator
        7: 'lift',       # Lift
    }.get(int(edgetype), 'other')


def allowed_kinds(route_type):
    """Which edge kinds are permitted for each route type."""
    if route_type == 'step_free':
        return {'lift', 'passage'}
    if route_type == 'smooth':
        return {'lift', 'passage', 'escalator'}
    # 'time' (fastest): every kind is allowed.
    return {'lift', 'passage', 'escalator', 'stair', 'other'}


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


def astar(start_id, end_id, route_type='time'):
    node_rows, edge_rows = load_graph()
    nodes = {int(node['id']): node for node in node_rows}
    if start_id not in nodes or end_id not in nodes:
        return None

    hierarchy = hierarchy_ids(nodes)
    adjacency = {node_id: [] for node_id in nodes}
    permitted = allowed_kinds(route_type)

    for edge in edge_rows:
        kind = edge_kind(edge.get('edgetype'))
        if kind not in permitted:
            continue

        first_id = int(edge['node1'])
        second_id = int(edge['node2'])
        if first_id not in nodes or second_id not in nodes:
            continue

        base_cost = float(edge['weight']) / WALK_SPEED_MPS  # metres -> seconds
        if not math.isfinite(base_cost) or base_cost < 0:
            raise ValueError('Edge weights must be finite and non-negative.')

        type_penalty = {
            'escalator': ESCALATOR_PENALTY,
            'stair': STAIR_PENALTY,
            'lift': LIFT_PENALTY,
        }.get(kind, 0.0)
        first_floor, first_building = hierarchy[first_id]
        second_floor, second_building = hierarchy[second_id]
        floor_cost = FLOOR_WEIGHT if first_floor != second_floor else 0.0
        building_cost = BUILDING_WEIGHT if first_building != second_building else 0.0
        cost = base_cost + type_penalty + floor_cost + building_cost

        adjacency[first_id].append((second_id, cost, edge))
        adjacency[second_id].append((first_id, cost, edge))

    # Straight-line time per metre. It is always an underestimate of the true
    # remaining time, so A* is guaranteed to return the least-time path.
    distance_scale = 1.0 / WALK_SPEED_MPS

    goal_pos = coordinates(nodes[end_id])
    goal_floor, goal_building = hierarchy[end_id]

    def heuristic(node_id):
        floor_id, building_id = hierarchy[node_id]

        # Straight-line time is only a valid lower bound when the two nodes
        # share the same floor coordinate frame (posx/posy are per-floor local
        # coordinates). Across floors the raw coordinates are offset by the
        # floor layout, so the straight-line distance overestimates the true
        # cost, breaking admissibility and making A* return suboptimal paths.
        spatial_cost = 0.0
        if floor_id == goal_floor:
            node_pos = coordinates(nodes[node_id])
            if node_pos is not None and goal_pos is not None:
                spatial_cost = distance_scale * math.hypot(
                    node_pos[0] - goal_pos[0],
                    node_pos[1] - goal_pos[1],
                )

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

    total_distance = sum(float(edge.get('weight') or 0) for edge in path_edges)

    return {
        'path': path,
        'edges': path_edges,
        'total_weight': scores[end_id],     # total estimated time in seconds
        'total_distance': total_distance,    # total walking distance in metres
        'nodes': {str(node_id): node for node_id, node in nodes.items()},
    }


def nearest_nodes(start_id, types, route_type='time', limit=10):
    """Return the nearest nodes of the given types from start_id.

    Runs Dijkstra once from the start node (respecting the route type's
    permitted edge kinds), then returns the closest reachable nodes whose
    type is in ``types``, ordered by least path cost (estimated seconds).
    """
    node_rows, edge_rows = load_graph()
    nodes = {int(node['id']): node for node in node_rows}
    if start_id not in nodes:
        return None

    hierarchy = hierarchy_ids(nodes)
    adjacency = {node_id: [] for node_id in nodes}
    permitted = allowed_kinds(route_type)

    for edge in edge_rows:
        kind = edge_kind(edge.get('edgetype'))
        if kind not in permitted:
            continue
        first_id = int(edge['node1'])
        second_id = int(edge['node2'])
        if first_id not in nodes or second_id not in nodes:
            continue

        base_cost = float(edge['weight']) / WALK_SPEED_MPS  # metres -> seconds
        if not math.isfinite(base_cost) or base_cost < 0:
            raise ValueError('Edge weights must be finite and non-negative.')

        type_penalty = {
            'escalator': ESCALATOR_PENALTY,
            'stair': STAIR_PENALTY,
            'lift': LIFT_PENALTY,
        }.get(kind, 0.0)
        first_floor, first_building = hierarchy[first_id]
        second_floor, second_building = hierarchy[second_id]
        floor_cost = FLOOR_WEIGHT if first_floor != second_floor else 0.0
        building_cost = BUILDING_WEIGHT if first_building != second_building else 0.0
        cost = base_cost + type_penalty + floor_cost + building_cost

        adjacency[first_id].append((second_id, cost, edge))
        adjacency[second_id].append((first_id, cost, edge))

    dist = {start_id: 0.0}
    previous = {}
    queue = [(0.0, start_id)]
    while queue:
        current_cost, current_id = heapq.heappop(queue)
        if current_cost > dist.get(current_id, math.inf):
            continue
        for neighbor_id, edge_cost, edge in adjacency[current_id]:
            candidate_cost = current_cost + edge_cost
            if candidate_cost < dist.get(neighbor_id, math.inf):
                dist[neighbor_id] = candidate_cost
                previous[neighbor_id] = (current_id, edge)
                heapq.heappush(queue, (candidate_cost, neighbor_id))

    wanted = set(types)
    candidates = [
        (dist[node_id], node_id)
        for node_id, node in nodes.items()
        if int(node.get('type')) in wanted and node_id in dist
    ]
    candidates.sort(key=lambda item: item[0])

    results = []
    for cost, node_id in candidates[:limit]:
        path = [node_id]
        path_edges = []
        cursor = node_id
        while cursor in previous:
            parent_id, edge = previous[cursor]
            path_edges.insert(0, edge)
            path.insert(0, parent_id)
            cursor = parent_id
        total_distance = sum(float(edge.get('weight') or 0) for edge in path_edges)
        results.append({
            'node_id': node_id,
            'total_weight': cost,
            'total_distance': total_distance,
            'path': path,
            'edges': path_edges,
        })

    return {'start_id': start_id, 'results': results}


@app.route('/api/route', methods=['GET', 'OPTIONS'])
def route():
    try:
        start_id = int(request.args.get('from', '18'))
        end_id = int(request.args.get('to', '47'))
    except (TypeError, ValueError):
        return jsonify({'ok': False, 'error': '"from" and "to" must be integer node IDs.'}), 400

    route_type = str(request.args.get('route_type', '')).strip().lower()
    if route_type not in ('time', 'smooth', 'step_free'):
        # Backward compatibility with the old step_free boolean.
        step_free = request.args.get('step_free', '0') == '1'
        route_type = 'step_free' if step_free else 'time'

    try:
        result = astar(start_id, end_id, route_type)
    except (pymysql.MySQLError, ValueError) as error:
        app.logger.exception('Route calculation failed')
        return jsonify({'ok': False, 'error': 'Route calculation failed.'}), 500

    if result is None:
        return jsonify({'ok': False, 'error': 'No accessible route was found.'}), 404
    return jsonify({'ok': True, 'data': result})


@app.route('/api/nearest', methods=['GET', 'OPTIONS'])
def nearest():
    try:
        start_id = int(request.args.get('from', ''))
    except (TypeError, ValueError):
        return jsonify({'ok': False, 'error': '"from" must be an integer node ID.'}), 400

    types_param = str(request.args.get('types', '')).strip()
    try:
        types = [int(t) for t in types_param.split(',') if t.strip() != '']
    except ValueError:
        return jsonify({'ok': False, 'error': '"types" must be comma-separated integers.'}), 400
    if not types:
        return jsonify({'ok': False, 'error': 'No node types supplied.'}), 400

    route_type = str(request.args.get('route_type', '')).strip().lower()
    if route_type not in ('time', 'smooth', 'step_free'):
        step_free = request.args.get('step_free', '0') == '1'
        route_type = 'step_free' if step_free else 'time'

    try:
        limit = min(int(request.args.get('n', '10')), 50)
    except (TypeError, ValueError):
        limit = 10

    try:
        result = nearest_nodes(start_id, types, route_type, limit)
    except (pymysql.MySQLError, ValueError) as error:
        app.logger.exception('Nearest calculation failed')
        return jsonify({'ok': False, 'error': 'Nearest calculation failed.'}), 500

    if result is None:
        return jsonify({'ok': False, 'error': 'No reachable resource of that type was found.'}), 404
    return jsonify({'ok': True, 'data': result})


if __name__ == '__main__':
    app.run(host='127.0.0.1', port=5000)
