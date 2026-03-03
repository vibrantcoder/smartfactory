<?php

app('router')->setCompiledRoutes(
    array (
  'compiled' => 
  array (
    0 => false,
    1 => 
    array (
      '/sanctum/csrf-cookie' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'sanctum.csrf-cookie',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/iot/ingest' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'iot.ingest',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/iot/ingest/batch' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'iot.ingest.batch',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/v1/auth/login' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.auth.login',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/v1/auth/logout' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.auth.logout',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/v1/auth/me' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.auth.me',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/v1/factories' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.factories.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'v1.factories.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/v1/machines' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.machines.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'v1.machines.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/v1/downtimes' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.downtimes.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'v1.downtimes.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/v1/downtime-reasons' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.downtime-reasons.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'v1.downtime-reasons.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/v1/customers' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.customers.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'v1.customers.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/v1/parts' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.parts.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'v1.parts.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/v1/process-masters' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.process-masters.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'v1.process-masters.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/v1/process-masters-palette' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.generated::IVjD6Ai9OjYej7mT',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/v1/production-plans' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.production-plans.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'v1.production-plans.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/v1/production-actuals' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.production-actuals.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'v1.production-actuals.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/v1/shifts' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.shifts',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/v1/iot/status' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.iot.status',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/api/v1/iot/oee' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.iot.oee.factory',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/up' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::kcsMpe2lHOT3VeKE',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/login' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'login',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'admin.login',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/logout' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'admin.logout',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'generated::j5xZCcFP8qduP3Ql',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/admin/dashboard' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'admin.dashboard',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/admin/users' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'admin.users.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'admin.users.store',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/admin/roles' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'admin.roles.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/admin/customers' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'admin.customers.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/admin/parts' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'admin.parts.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/admin/production/plans' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'admin.production.plans.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/admin/process-masters' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'admin.process-masters.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/admin/iot' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'admin.iot.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/employee/login' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'employee.login',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'employee.do-login',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/employee/logout' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'employee.logout',
          ),
          1 => NULL,
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/employee/no-machine' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'employee.no-machine',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/employee/dashboard' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'employee.dashboard',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      '/employee/jobs' => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'employee.jobs.index',
          ),
          1 => NULL,
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
    ),
    2 => 
    array (
      0 => '{^(?|/a(?|pi/v1/(?|factories/([^/]++)(?|(*:42)|/settings(?|(*:61)))|machines/([^/]++)(?|(*:90)|/logs(?|(*:105)))|downtime(?|s/([^/]++)(?|(*:139))|\\-reasons/([^/]++)(?|(*:169)))|customers/([^/]++)(?|(*:200))|p(?|arts/([^/]++)(?|(*:229)|/processes(*:247))|ro(?|cess\\-masters/(?|([^/]++)(?|(*:289))|preview\\-cycle\\-time(*:318))|duction\\-(?|plans/([^/]++)(?|(*:356)|/analysis(*:373))|actuals/([^/]++)(?|(*:401)))))|analytics/(?|parts/([^/]++)/targets(*:448)|factories/([^/]++)/daily\\-targets(*:489))|iot/machines/([^/]++)/(?|chart(*:528)|timeline(*:544)|export(*:558)|oee(*:569)))|dmin/(?|users/([^/]++)(?|(*:604)|/(?|assign\\-(?|role(*:631)|machine(*:646))|revoke\\-role(*:667)))|roles/([^/]++)(?|(*:694)|/(?|matrix(*:712)|permissions(*:731)))|parts/([^/]++)(?|/routing(*:766)|(*:774))|iot/machines/([^/]++)/export(*:811)))|/storage/(.*)(?|(*:837)))/?$}sDu',
    ),
    3 => 
    array (
      42 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.factories.show',
          ),
          1 => 
          array (
            0 => 'factory',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'v1.factories.update',
          ),
          1 => 
          array (
            0 => 'factory',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'v1.factories.destroy',
          ),
          1 => 
          array (
            0 => 'factory',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      61 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.',
          ),
          1 => 
          array (
            0 => 'factory',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'v1.generated::lPbbjkOHpizFjU5M',
          ),
          1 => 
          array (
            0 => 'factory',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      90 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.machines.show',
          ),
          1 => 
          array (
            0 => 'machine',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'v1.machines.update',
          ),
          1 => 
          array (
            0 => 'machine',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'v1.machines.destroy',
          ),
          1 => 
          array (
            0 => 'machine',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      105 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.generated::jalzUXFPtd6oFxaP',
          ),
          1 => 
          array (
            0 => 'machine',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'v1.generated::mAf4daU0EPTK2BK4',
          ),
          1 => 
          array (
            0 => 'machine',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      139 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.downtimes.show',
          ),
          1 => 
          array (
            0 => 'downtime',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'v1.downtimes.update',
          ),
          1 => 
          array (
            0 => 'downtime',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'v1.downtimes.destroy',
          ),
          1 => 
          array (
            0 => 'downtime',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      169 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.downtime-reasons.show',
          ),
          1 => 
          array (
            0 => 'downtime_reason',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'v1.downtime-reasons.update',
          ),
          1 => 
          array (
            0 => 'downtime_reason',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'v1.downtime-reasons.destroy',
          ),
          1 => 
          array (
            0 => 'downtime_reason',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      200 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.customers.show',
          ),
          1 => 
          array (
            0 => 'customer',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'v1.customers.update',
          ),
          1 => 
          array (
            0 => 'customer',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'v1.customers.destroy',
          ),
          1 => 
          array (
            0 => 'customer',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      229 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.parts.show',
          ),
          1 => 
          array (
            0 => 'part',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'v1.parts.update',
          ),
          1 => 
          array (
            0 => 'part',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'v1.parts.destroy',
          ),
          1 => 
          array (
            0 => 'part',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      247 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.generated::HUqdex5UFNBKI776',
          ),
          1 => 
          array (
            0 => 'part',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      289 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.process-masters.show',
          ),
          1 => 
          array (
            0 => 'process_master',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'v1.process-masters.update',
          ),
          1 => 
          array (
            0 => 'process_master',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'v1.process-masters.destroy',
          ),
          1 => 
          array (
            0 => 'process_master',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      318 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.generated::52MKxBoIU1X5chcb',
          ),
          1 => 
          array (
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      356 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.production-plans.show',
          ),
          1 => 
          array (
            0 => 'production_plan',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'v1.production-plans.update',
          ),
          1 => 
          array (
            0 => 'production_plan',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'v1.production-plans.destroy',
          ),
          1 => 
          array (
            0 => 'production_plan',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      373 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.generated::srWVRMyjiKPPcWRD',
          ),
          1 => 
          array (
            0 => 'plan',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      401 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.production-actuals.show',
          ),
          1 => 
          array (
            0 => 'production_actual',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'v1.production-actuals.update',
          ),
          1 => 
          array (
            0 => 'production_actual',
          ),
          2 => 
          array (
            'PUT' => 0,
            'PATCH' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => 
          array (
            '_route' => 'v1.production-actuals.destroy',
          ),
          1 => 
          array (
            0 => 'production_actual',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      448 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.analytics.',
          ),
          1 => 
          array (
            0 => 'part',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      489 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.analytics.generated::yW3iYZOfV200yxfP',
          ),
          1 => 
          array (
            0 => 'factory',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      528 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.iot.machine.chart',
          ),
          1 => 
          array (
            0 => 'machine',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      544 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.iot.machine.timeline',
          ),
          1 => 
          array (
            0 => 'machine',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      558 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.iot.machine.export',
          ),
          1 => 
          array (
            0 => 'machine',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      569 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'v1.iot.machine.oee',
          ),
          1 => 
          array (
            0 => 'machine',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      604 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'admin.users.show',
          ),
          1 => 
          array (
            0 => 'user',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'admin.users.update',
          ),
          1 => 
          array (
            0 => 'user',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      631 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'admin.users.assign-role',
          ),
          1 => 
          array (
            0 => 'user',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      646 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'admin.users.assign-machine',
          ),
          1 => 
          array (
            0 => 'user',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      667 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'admin.users.revoke-role',
          ),
          1 => 
          array (
            0 => 'user',
          ),
          2 => 
          array (
            'DELETE' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      694 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'admin.roles.show',
          ),
          1 => 
          array (
            0 => 'role',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      712 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'admin.roles.matrix',
          ),
          1 => 
          array (
            0 => 'role',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      731 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'admin.roles.permissions',
          ),
          1 => 
          array (
            0 => 'role',
          ),
          2 => 
          array (
            'POST' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      766 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'admin.parts.routing.edit',
          ),
          1 => 
          array (
            0 => 'part',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      774 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'admin.parts.show',
          ),
          1 => 
          array (
            0 => 'part',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
      ),
      811 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'admin.iot.export',
          ),
          1 => 
          array (
            0 => 'machine',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => false,
          6 => NULL,
        ),
      ),
      837 => 
      array (
        0 => 
        array (
          0 => 
          array (
            '_route' => 'storage.local',
          ),
          1 => 
          array (
            0 => 'path',
          ),
          2 => 
          array (
            'GET' => 0,
            'HEAD' => 1,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        1 => 
        array (
          0 => 
          array (
            '_route' => 'storage.local.upload',
          ),
          1 => 
          array (
            0 => 'path',
          ),
          2 => 
          array (
            'PUT' => 0,
          ),
          3 => NULL,
          4 => false,
          5 => true,
          6 => NULL,
        ),
        2 => 
        array (
          0 => NULL,
          1 => NULL,
          2 => NULL,
          3 => NULL,
          4 => false,
          5 => false,
          6 => 0,
        ),
      ),
    ),
    4 => NULL,
  ),
  'attributes' => 
  array (
    'sanctum.csrf-cookie' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'sanctum/csrf-cookie',
      'action' => 
      array (
        'uses' => 'Laravel\\Sanctum\\Http\\Controllers\\CsrfCookieController@show',
        'controller' => 'Laravel\\Sanctum\\Http\\Controllers\\CsrfCookieController@show',
        'namespace' => NULL,
        'prefix' => 'sanctum',
        'where' => 
        array (
        ),
        'middleware' => 
        array (
          0 => 'web',
        ),
        'as' => 'sanctum.csrf-cookie',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'iot.ingest' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/iot/ingest',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Iot\\IotController@ingest',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Iot\\IotController@ingest',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'iot.ingest',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'iot.ingest.batch' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/iot/ingest/batch',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Iot\\IotController@ingestBatch',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Iot\\IotController@ingestBatch',
        'namespace' => NULL,
        'prefix' => 'api',
        'where' => 
        array (
        ),
        'as' => 'iot.ingest.batch',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.auth.login' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/v1/auth/login',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Auth\\AuthController@login',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Auth\\AuthController@login',
        'as' => 'v1.auth.login',
        'namespace' => NULL,
        'prefix' => 'api/v1/auth',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.auth.logout' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/v1/auth/logout',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Auth\\AuthController@logout',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Auth\\AuthController@logout',
        'as' => 'v1.auth.logout',
        'namespace' => NULL,
        'prefix' => 'api/v1/auth',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.auth.me' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/auth/me',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Auth\\AuthController@me',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Auth\\AuthController@me',
        'as' => 'v1.auth.me',
        'namespace' => NULL,
        'prefix' => 'api/v1/auth',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.factories.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/factories',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.factories.index',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Factory\\FactoryController@index',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Factory\\FactoryController@index',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.factories.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/v1/factories',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.factories.store',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Factory\\FactoryController@store',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Factory\\FactoryController@store',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.factories.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/factories/{factory}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.factories.show',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Factory\\FactoryController@show',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Factory\\FactoryController@show',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.factories.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/v1/factories/{factory}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.factories.update',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Factory\\FactoryController@update',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Factory\\FactoryController@update',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.factories.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/v1/factories/{factory}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.factories.destroy',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Factory\\FactoryController@destroy',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Factory\\FactoryController@destroy',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/factories/{factory}/settings',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Factory\\FactoryController@settings',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Factory\\FactoryController@settings',
        'as' => 'v1.',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.generated::lPbbjkOHpizFjU5M' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/v1/factories/{factory}/settings',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Factory\\FactoryController@updateSettings',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Factory\\FactoryController@updateSettings',
        'as' => 'v1.generated::lPbbjkOHpizFjU5M',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.machines.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/machines',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.machines.index',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Machine\\MachineController@index',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Machine\\MachineController@index',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.machines.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/v1/machines',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.machines.store',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Machine\\MachineController@store',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Machine\\MachineController@store',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.machines.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/machines/{machine}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.machines.show',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Machine\\MachineController@show',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Machine\\MachineController@show',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.machines.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/v1/machines/{machine}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.machines.update',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Machine\\MachineController@update',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Machine\\MachineController@update',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.machines.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/v1/machines/{machine}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.machines.destroy',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Machine\\MachineController@destroy',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Machine\\MachineController@destroy',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.generated::jalzUXFPtd6oFxaP' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/machines/{machine}/logs',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Machine\\MachineController@logs',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Machine\\MachineController@logs',
        'as' => 'v1.generated::jalzUXFPtd6oFxaP',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.generated::mAf4daU0EPTK2BK4' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/v1/machines/{machine}/logs',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Machine\\MachineController@recordLog',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Machine\\MachineController@recordLog',
        'as' => 'v1.generated::mAf4daU0EPTK2BK4',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.downtimes.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/downtimes',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.downtimes.index',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Downtime\\DowntimeController@index',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Downtime\\DowntimeController@index',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.downtimes.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/v1/downtimes',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.downtimes.store',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Downtime\\DowntimeController@store',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Downtime\\DowntimeController@store',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.downtimes.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/downtimes/{downtime}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.downtimes.show',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Downtime\\DowntimeController@show',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Downtime\\DowntimeController@show',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.downtimes.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/v1/downtimes/{downtime}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.downtimes.update',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Downtime\\DowntimeController@update',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Downtime\\DowntimeController@update',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.downtimes.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/v1/downtimes/{downtime}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.downtimes.destroy',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Downtime\\DowntimeController@destroy',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Downtime\\DowntimeController@destroy',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.downtime-reasons.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/downtime-reasons',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.downtime-reasons.index',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Downtime\\DowntimeReasonController@index',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Downtime\\DowntimeReasonController@index',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.downtime-reasons.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/v1/downtime-reasons',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.downtime-reasons.store',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Downtime\\DowntimeReasonController@store',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Downtime\\DowntimeReasonController@store',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.downtime-reasons.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/downtime-reasons/{downtime_reason}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.downtime-reasons.show',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Downtime\\DowntimeReasonController@show',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Downtime\\DowntimeReasonController@show',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.downtime-reasons.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/v1/downtime-reasons/{downtime_reason}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.downtime-reasons.update',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Downtime\\DowntimeReasonController@update',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Downtime\\DowntimeReasonController@update',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.downtime-reasons.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/v1/downtime-reasons/{downtime_reason}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.downtime-reasons.destroy',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Downtime\\DowntimeReasonController@destroy',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Downtime\\DowntimeReasonController@destroy',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.customers.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/customers',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.customers.index',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\CustomerController@index',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\CustomerController@index',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.customers.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/v1/customers',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.customers.store',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\CustomerController@store',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\CustomerController@store',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.customers.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/customers/{customer}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.customers.show',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\CustomerController@show',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\CustomerController@show',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.customers.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/v1/customers/{customer}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.customers.update',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\CustomerController@update',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\CustomerController@update',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.customers.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/v1/customers/{customer}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.customers.destroy',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\CustomerController@destroy',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\CustomerController@destroy',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.parts.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/parts',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.parts.index',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\PartController@index',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\PartController@index',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.parts.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/v1/parts',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.parts.store',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\PartController@store',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\PartController@store',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.parts.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/parts/{part}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.parts.show',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\PartController@show',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\PartController@show',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.parts.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/v1/parts/{part}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.parts.update',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\PartController@update',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\PartController@update',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.parts.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/v1/parts/{part}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.parts.destroy',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\PartController@destroy',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\PartController@destroy',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.generated::HUqdex5UFNBKI776' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'api/v1/parts/{part}/processes',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\PartController@syncProcesses',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\PartController@syncProcesses',
        'as' => 'v1.generated::HUqdex5UFNBKI776',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.process-masters.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/process-masters',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.process-masters.index',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProcessMasterController@index',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProcessMasterController@index',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.process-masters.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/v1/process-masters',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.process-masters.store',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProcessMasterController@store',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProcessMasterController@store',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.process-masters.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/process-masters/{process_master}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.process-masters.show',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProcessMasterController@show',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProcessMasterController@show',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.process-masters.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/v1/process-masters/{process_master}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.process-masters.update',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProcessMasterController@update',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProcessMasterController@update',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.process-masters.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/v1/process-masters/{process_master}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.process-masters.destroy',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProcessMasterController@destroy',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProcessMasterController@destroy',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.generated::IVjD6Ai9OjYej7mT' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/process-masters-palette',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProcessMasterController@palette',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProcessMasterController@palette',
        'as' => 'v1.generated::IVjD6Ai9OjYej7mT',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.generated::52MKxBoIU1X5chcb' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/v1/process-masters/preview-cycle-time',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProcessMasterController@previewCycleTime',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProcessMasterController@previewCycleTime',
        'as' => 'v1.generated::52MKxBoIU1X5chcb',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.production-plans.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/production-plans',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.production-plans.index',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionPlanController@index',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionPlanController@index',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.production-plans.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/v1/production-plans',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.production-plans.store',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionPlanController@store',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionPlanController@store',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.production-plans.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/production-plans/{production_plan}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.production-plans.show',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionPlanController@show',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionPlanController@show',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.production-plans.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/v1/production-plans/{production_plan}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.production-plans.update',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionPlanController@update',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionPlanController@update',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.production-plans.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/v1/production-plans/{production_plan}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.production-plans.destroy',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionPlanController@destroy',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionPlanController@destroy',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.generated::srWVRMyjiKPPcWRD' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/production-plans/{plan}/analysis',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionAnalysisController@planAnalysis',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionAnalysisController@planAnalysis',
        'as' => 'v1.generated::srWVRMyjiKPPcWRD',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.production-actuals.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/production-actuals',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.production-actuals.index',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionActualController@index',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionActualController@index',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.production-actuals.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'api/v1/production-actuals',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.production-actuals.store',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionActualController@store',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionActualController@store',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.production-actuals.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/production-actuals/{production_actual}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.production-actuals.show',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionActualController@show',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionActualController@show',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.production-actuals.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
        1 => 'PATCH',
      ),
      'uri' => 'api/v1/production-actuals/{production_actual}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.production-actuals.update',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionActualController@update',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionActualController@update',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.production-actuals.destroy' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'api/v1/production-actuals/{production_actual}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'as' => 'v1.production-actuals.destroy',
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionActualController@destroy',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionActualController@destroy',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.analytics.' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/analytics/parts/{part}/targets',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionAnalysisController@partTargets',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionAnalysisController@partTargets',
        'as' => 'v1.analytics.',
        'namespace' => NULL,
        'prefix' => 'api/v1/analytics',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.analytics.generated::yW3iYZOfV200yxfP' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/analytics/factories/{factory}/daily-targets',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionAnalysisController@factoryDailyTargets',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Production\\ProductionAnalysisController@factoryDailyTargets',
        'as' => 'v1.analytics.generated::yW3iYZOfV200yxfP',
        'namespace' => NULL,
        'prefix' => 'api/v1/analytics',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.shifts' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/shifts',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Iot\\IotController@shifts',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Iot\\IotController@shifts',
        'as' => 'v1.shifts',
        'namespace' => NULL,
        'prefix' => 'api/v1',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.iot.status' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/iot/status',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Iot\\IotController@status',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Iot\\IotController@status',
        'as' => 'v1.iot.status',
        'namespace' => NULL,
        'prefix' => 'api/v1/iot',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.iot.machine.chart' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/iot/machines/{machine}/chart',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Iot\\IotController@machineChart',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Iot\\IotController@machineChart',
        'as' => 'v1.iot.machine.chart',
        'namespace' => NULL,
        'prefix' => 'api/v1/iot',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.iot.machine.timeline' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/iot/machines/{machine}/timeline',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Iot\\IotController@machineTimeline',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Iot\\IotController@machineTimeline',
        'as' => 'v1.iot.machine.timeline',
        'namespace' => NULL,
        'prefix' => 'api/v1/iot',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.iot.machine.export' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/iot/machines/{machine}/export',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Iot\\IotController@machineExport',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Iot\\IotController@machineExport',
        'as' => 'v1.iot.machine.export',
        'namespace' => NULL,
        'prefix' => 'api/v1/iot',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.iot.oee.factory' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/iot/oee',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Iot\\OeeController@factoryOee',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Iot\\OeeController@factoryOee',
        'as' => 'v1.iot.oee.factory',
        'namespace' => NULL,
        'prefix' => 'api/v1/iot',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'v1.iot.machine.oee' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'api/v1/iot/machines/{machine}/oee',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'api',
          1 => 'auth:sanctum',
          2 => 'factory.scope',
          3 => 'factory.member',
        ),
        'uses' => 'App\\Http\\Controllers\\Api\\V1\\Iot\\OeeController@machineOee',
        'controller' => 'App\\Http\\Controllers\\Api\\V1\\Iot\\OeeController@machineOee',
        'as' => 'v1.iot.machine.oee',
        'namespace' => NULL,
        'prefix' => 'api/v1/iot',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::kcsMpe2lHOT3VeKE' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'up',
      'action' => 
      array (
        'uses' => 'O:55:"Laravel\\SerializableClosure\\UnsignedSerializableClosure":1:{s:12:"serializable";O:46:"Laravel\\SerializableClosure\\Serializers\\Native":5:{s:3:"use";a:0:{}s:8:"function";s:832:"function () {
                    $exception = null;

                    try {
                        \\Illuminate\\Support\\Facades\\Event::dispatch(new \\Illuminate\\Foundation\\Events\\DiagnosingHealth);
                    } catch (\\Throwable $e) {
                        if (app()->hasDebugModeEnabled()) {
                            throw $e;
                        }

                        report($e);

                        $exception = $e->getMessage();
                    }

                    return response(\\Illuminate\\Support\\Facades\\View::file(\'D:\\\\xampp\\\\htdocs\\\\smartfactory\\\\vendor\\\\laravel\\\\framework\\\\src\\\\Illuminate\\\\Foundation\\\\Configuration\'.\'/../resources/health-up.blade.php\', [
                        \'exception\' => $exception,
                    ]), status: $exception ? 500 : 200);
                }";s:5:"scope";s:54:"Illuminate\\Foundation\\Configuration\\ApplicationBuilder";s:4:"this";N;s:4:"self";s:32:"000000000000038d0000000000000000";}}',
        'as' => 'generated::kcsMpe2lHOT3VeKE',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'login' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'login',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\Auth\\LoginController@showForm',
        'controller' => 'App\\Http\\Controllers\\Admin\\Auth\\LoginController@showForm',
        'namespace' => NULL,
        'prefix' => '',
        'where' => 
        array (
        ),
        'as' => 'login',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.login' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'login',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\Auth\\LoginController@login',
        'controller' => 'App\\Http\\Controllers\\Admin\\Auth\\LoginController@login',
        'namespace' => NULL,
        'prefix' => '',
        'where' => 
        array (
        ),
        'as' => 'admin.login',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.logout' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'logout',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\Auth\\LoginController@logout',
        'controller' => 'App\\Http\\Controllers\\Admin\\Auth\\LoginController@logout',
        'namespace' => NULL,
        'prefix' => '',
        'where' => 
        array (
        ),
        'as' => 'admin.logout',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'generated::j5xZCcFP8qduP3Ql' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => '/',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
        ),
        'uses' => 'O:55:"Laravel\\SerializableClosure\\UnsignedSerializableClosure":1:{s:12:"serializable";O:46:"Laravel\\SerializableClosure\\Serializers\\Native":5:{s:3:"use";a:0:{}s:8:"function";s:46:"fn () => \\redirect()->route(\'admin.dashboard\')";s:5:"scope";s:37:"Illuminate\\Routing\\RouteFileRegistrar";s:4:"this";N;s:4:"self";s:32:"00000000000006770000000000000000";}}',
        'namespace' => NULL,
        'prefix' => '',
        'where' => 
        array (
        ),
        'as' => 'generated::j5xZCcFP8qduP3Ql',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.dashboard' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'admin/dashboard',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'admin.role',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\Dashboard\\DashboardController@index',
        'controller' => 'App\\Http\\Controllers\\Admin\\Dashboard\\DashboardController@index',
        'as' => 'admin.dashboard',
        'namespace' => NULL,
        'prefix' => '/admin',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.users.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'admin/users',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'admin.role',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\User\\UserController@index',
        'controller' => 'App\\Http\\Controllers\\Admin\\User\\UserController@index',
        'as' => 'admin.users.index',
        'namespace' => NULL,
        'prefix' => 'admin/users',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.users.store' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'admin/users',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'admin.role',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\User\\UserController@store',
        'controller' => 'App\\Http\\Controllers\\Admin\\User\\UserController@store',
        'as' => 'admin.users.store',
        'namespace' => NULL,
        'prefix' => 'admin/users',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.users.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'admin/users/{user}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'admin.role',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\User\\UserController@show',
        'controller' => 'App\\Http\\Controllers\\Admin\\User\\UserController@show',
        'as' => 'admin.users.show',
        'namespace' => NULL,
        'prefix' => 'admin/users',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.users.update' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'admin/users/{user}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'admin.role',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\User\\UserController@update',
        'controller' => 'App\\Http\\Controllers\\Admin\\User\\UserController@update',
        'as' => 'admin.users.update',
        'namespace' => NULL,
        'prefix' => 'admin/users',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.users.assign-role' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'admin/users/{user}/assign-role',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'admin.role',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\User\\UserController@assignRole',
        'controller' => 'App\\Http\\Controllers\\Admin\\User\\UserController@assignRole',
        'as' => 'admin.users.assign-role',
        'namespace' => NULL,
        'prefix' => 'admin/users',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.users.revoke-role' => 
    array (
      'methods' => 
      array (
        0 => 'DELETE',
      ),
      'uri' => 'admin/users/{user}/revoke-role',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'admin.role',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\User\\UserController@revokeRole',
        'controller' => 'App\\Http\\Controllers\\Admin\\User\\UserController@revokeRole',
        'as' => 'admin.users.revoke-role',
        'namespace' => NULL,
        'prefix' => 'admin/users',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.roles.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'admin/roles',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'admin.role',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\Role\\RoleController@index',
        'controller' => 'App\\Http\\Controllers\\Admin\\Role\\RoleController@index',
        'as' => 'admin.roles.index',
        'namespace' => NULL,
        'prefix' => 'admin/roles',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.roles.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'admin/roles/{role}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'admin.role',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\Role\\RoleController@show',
        'controller' => 'App\\Http\\Controllers\\Admin\\Role\\RoleController@show',
        'as' => 'admin.roles.show',
        'namespace' => NULL,
        'prefix' => 'admin/roles',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.roles.matrix' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'admin/roles/{role}/matrix',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'admin.role',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\Role\\RoleController@matrix',
        'controller' => 'App\\Http\\Controllers\\Admin\\Role\\RoleController@matrix',
        'as' => 'admin.roles.matrix',
        'namespace' => NULL,
        'prefix' => 'admin/roles',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.roles.permissions' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'admin/roles/{role}/permissions',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'admin.role',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\Role\\RoleController@syncPermissions',
        'controller' => 'App\\Http\\Controllers\\Admin\\Role\\RoleController@syncPermissions',
        'as' => 'admin.roles.permissions',
        'namespace' => NULL,
        'prefix' => 'admin/roles',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.customers.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'admin/customers',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'admin.role',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\Production\\CustomerController@index',
        'controller' => 'App\\Http\\Controllers\\Admin\\Production\\CustomerController@index',
        'as' => 'admin.customers.index',
        'namespace' => NULL,
        'prefix' => 'admin/customers',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.parts.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'admin/parts',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'admin.role',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\Part\\PartRoutingController@index',
        'controller' => 'App\\Http\\Controllers\\Admin\\Part\\PartRoutingController@index',
        'as' => 'admin.parts.index',
        'namespace' => NULL,
        'prefix' => 'admin/parts',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.parts.routing.edit' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'admin/parts/{part}/routing',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'admin.role',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\Part\\PartRoutingController@edit',
        'controller' => 'App\\Http\\Controllers\\Admin\\Part\\PartRoutingController@edit',
        'as' => 'admin.parts.routing.edit',
        'namespace' => NULL,
        'prefix' => 'admin/parts',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.parts.show' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'admin/parts/{part}',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'admin.role',
        ),
        'uses' => 'O:55:"Laravel\\SerializableClosure\\UnsignedSerializableClosure":1:{s:12:"serializable";O:46:"Laravel\\SerializableClosure\\Serializers\\Native":5:{s:3:"use";a:0:{}s:8:"function";s:88:"fn (\\App\\Domain\\Production\\Models\\Part $part) => \\redirect()->route(\'admin.parts.index\')";s:5:"scope";s:37:"Illuminate\\Routing\\RouteFileRegistrar";s:4:"this";N;s:4:"self";s:32:"000000000000068b0000000000000000";}}',
        'as' => 'admin.parts.show',
        'namespace' => NULL,
        'prefix' => 'admin/parts',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.production.plans.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'admin/production/plans',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'admin.role',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\Production\\ProductionPlanController@index',
        'controller' => 'App\\Http\\Controllers\\Admin\\Production\\ProductionPlanController@index',
        'as' => 'admin.production.plans.index',
        'namespace' => NULL,
        'prefix' => 'admin/production',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.process-masters.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'admin/process-masters',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'admin.role',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\ProcessMaster\\ProcessMasterController@index',
        'controller' => 'App\\Http\\Controllers\\Admin\\ProcessMaster\\ProcessMasterController@index',
        'as' => 'admin.process-masters.index',
        'namespace' => NULL,
        'prefix' => 'admin/process-masters',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.iot.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'admin/iot',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'admin.role',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\Iot\\IotDashboardController@index',
        'controller' => 'App\\Http\\Controllers\\Admin\\Iot\\IotDashboardController@index',
        'as' => 'admin.iot.index',
        'namespace' => NULL,
        'prefix' => 'admin/iot',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.iot.export' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'admin/iot/machines/{machine}/export',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'admin.role',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\Iot\\IotDashboardController@export',
        'controller' => 'App\\Http\\Controllers\\Admin\\Iot\\IotDashboardController@export',
        'as' => 'admin.iot.export',
        'namespace' => NULL,
        'prefix' => 'admin/iot',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'admin.users.assign-machine' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'admin/users/{user}/assign-machine',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'admin.role',
        ),
        'uses' => 'App\\Http\\Controllers\\Admin\\User\\UserController@assignMachine',
        'controller' => 'App\\Http\\Controllers\\Admin\\User\\UserController@assignMachine',
        'as' => 'admin.users.assign-machine',
        'namespace' => NULL,
        'prefix' => '/admin',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employee.login' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'employee/login',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
        ),
        'uses' => 'App\\Http\\Controllers\\Employee\\Auth\\LoginController@showForm',
        'controller' => 'App\\Http\\Controllers\\Employee\\Auth\\LoginController@showForm',
        'as' => 'employee.login',
        'namespace' => NULL,
        'prefix' => '/employee',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employee.do-login' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'employee/login',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
        ),
        'uses' => 'App\\Http\\Controllers\\Employee\\Auth\\LoginController@login',
        'controller' => 'App\\Http\\Controllers\\Employee\\Auth\\LoginController@login',
        'as' => 'employee.do-login',
        'namespace' => NULL,
        'prefix' => '/employee',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employee.logout' => 
    array (
      'methods' => 
      array (
        0 => 'POST',
      ),
      'uri' => 'employee/logout',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
        ),
        'uses' => 'App\\Http\\Controllers\\Employee\\Auth\\LoginController@logout',
        'controller' => 'App\\Http\\Controllers\\Employee\\Auth\\LoginController@logout',
        'as' => 'employee.logout',
        'namespace' => NULL,
        'prefix' => '/employee',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employee.no-machine' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'employee/no-machine',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'employee.role',
        ),
        'uses' => 'App\\Http\\Controllers\\Employee\\Dashboard\\DashboardController@noMachine',
        'controller' => 'App\\Http\\Controllers\\Employee\\Dashboard\\DashboardController@noMachine',
        'as' => 'employee.no-machine',
        'namespace' => NULL,
        'prefix' => '/employee',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employee.dashboard' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'employee/dashboard',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'employee.role',
          4 => 'employee.machine',
        ),
        'uses' => 'App\\Http\\Controllers\\Employee\\Dashboard\\DashboardController@index',
        'controller' => 'App\\Http\\Controllers\\Employee\\Dashboard\\DashboardController@index',
        'as' => 'employee.dashboard',
        'namespace' => NULL,
        'prefix' => '/employee',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'employee.jobs.index' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'employee/jobs',
      'action' => 
      array (
        'middleware' => 
        array (
          0 => 'web',
          1 => 'auth:web',
          2 => 'factory.scope',
          3 => 'employee.role',
          4 => 'employee.machine',
        ),
        'uses' => 'App\\Http\\Controllers\\Employee\\Production\\JobsController@index',
        'controller' => 'App\\Http\\Controllers\\Employee\\Production\\JobsController@index',
        'as' => 'employee.jobs.index',
        'namespace' => NULL,
        'prefix' => '/employee',
        'where' => 
        array (
        ),
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'storage.local' => 
    array (
      'methods' => 
      array (
        0 => 'GET',
        1 => 'HEAD',
      ),
      'uri' => 'storage/{path}',
      'action' => 
      array (
        'uses' => 'O:55:"Laravel\\SerializableClosure\\UnsignedSerializableClosure":1:{s:12:"serializable";O:46:"Laravel\\SerializableClosure\\Serializers\\Native":5:{s:3:"use";a:3:{s:4:"disk";s:5:"local";s:6:"config";a:5:{s:6:"driver";s:5:"local";s:4:"root";s:48:"D:\\xampp\\htdocs\\smartfactory\\storage\\app/private";s:5:"serve";b:1;s:5:"throw";b:0;s:6:"report";b:0;}s:12:"isProduction";b:0;}s:8:"function";s:323:"function (\\Illuminate\\Http\\Request $request, string $path) use ($disk, $config, $isProduction) {
                    return (new \\Illuminate\\Filesystem\\ServeFile(
                        $disk,
                        $config,
                        $isProduction
                    ))($request, $path);
                }";s:5:"scope";s:47:"Illuminate\\Filesystem\\FilesystemServiceProvider";s:4:"this";N;s:4:"self";s:32:"00000000000006790000000000000000";}}',
        'as' => 'storage.local',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
        'path' => '.*',
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
    'storage.local.upload' => 
    array (
      'methods' => 
      array (
        0 => 'PUT',
      ),
      'uri' => 'storage/{path}',
      'action' => 
      array (
        'uses' => 'O:55:"Laravel\\SerializableClosure\\UnsignedSerializableClosure":1:{s:12:"serializable";O:46:"Laravel\\SerializableClosure\\Serializers\\Native":5:{s:3:"use";a:3:{s:4:"disk";s:5:"local";s:6:"config";a:5:{s:6:"driver";s:5:"local";s:4:"root";s:48:"D:\\xampp\\htdocs\\smartfactory\\storage\\app/private";s:5:"serve";b:1;s:5:"throw";b:0;s:6:"report";b:0;}s:12:"isProduction";b:0;}s:8:"function";s:325:"function (\\Illuminate\\Http\\Request $request, string $path) use ($disk, $config, $isProduction) {
                    return (new \\Illuminate\\Filesystem\\ReceiveFile(
                        $disk,
                        $config,
                        $isProduction
                    ))($request, $path);
                }";s:5:"scope";s:47:"Illuminate\\Filesystem\\FilesystemServiceProvider";s:4:"this";N;s:4:"self";s:32:"00000000000006930000000000000000";}}',
        'as' => 'storage.local.upload',
      ),
      'fallback' => false,
      'defaults' => 
      array (
      ),
      'wheres' => 
      array (
        'path' => '.*',
      ),
      'bindingFields' => 
      array (
      ),
      'lockSeconds' => NULL,
      'waitSeconds' => NULL,
      'withTrashed' => false,
    ),
  ),
)
);
