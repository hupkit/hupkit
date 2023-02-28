Upgrade Hubkit
==============

Upgrade to v1.2.0-BETA4
-----------------------

The configuration format was changed to allow for more advanced features, change
the `schema_version` to 2 and update the new structure.

**Note:** The old configuration format still works (until the next major release of Hubkit) but will give a warning.

For config.php the new structure as follows, see the [Configuration](config.md) chapter for all options.

```diff
-    'schema_version' => 1,
+    'schema_version' => 2,

-//    'repos' => [
-//        'github.com' => [
-//            'park-manager/park-manager' => [
-//                'sync-tags' => true,
-//                'split' => [
-//                    'src/Bundle/CoreBundle' => 'git@github.com:park-manager/core-bundle.git',
-//                    'src/Bundle/UserBundle' => 'git@github.com:park-manager/user-bundle.git',
-//                    'doc' => ['git@github.com:park-manager/doc.git', 'sync-tags' => false],
-//                ],
-//            ],
-//        ],
-//    ],
+
+    'repositories' => [
+        'github.com' => [
+            'park-manager/park-manager' => [
+                'branches' => [
+                    ':default' => [
+                        'sync-tags' => true,
+                        'split' => [
+                            'src/Module/CoreModule' => 'git@github.com:hubkit-sandbox/core-module.git',
+                            'src/Module/WebhostingModule' => 'git@github.com:hubkit-sandbox/webhosting-module.git',
+                            'docs' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => false],
+                        ],
+                    ],
+                    '1.0' => false, // Disabled
+                    '2.x' => [
+                        'upmerge' => false,
+                        // Split's is inherited and merged from ':default'
+                        'split' => [
+                            'src/Module/DomainRegModule' => 'git@github.com:hubkit-sandbox/webhosting-module.git',
+                        ],
+                    ],
+                    '3.x' => [
+                        'sync-tags' => null, // Inherit from the root configuration.
+                        'split' => [
+                            'src/Module/WebhostingModule' => false,
+                            'src/Module/DomainRegModule' => 'git@github.com:hubkit-sandbox/webhosting-module.git',
+                        ],
+                    ],
+                    '4.1' => [
+                        'ignore-default' => true, // Nothing is inherited from ':default'
+                        'sync-tags' => false,
+                        'src/Bundle/CoreBundle' => 'git@github.com:hubkit-sandbox/core-module.git',
+                        'src/Bundle/WebhostingBundle' => 'git@github.com:hubkit-sandbox/webhosting-module.git',
+                        'docs' => ['url' => 'git@github.com:park-manager/doc.git', 'sync-tags' => false],
+                    ],
+                ],
+            ],
+        ],
+    ],
```

