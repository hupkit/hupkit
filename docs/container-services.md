Container Services
==================

Hook scripts have access to the application workflow, and can use a
number of services for special operations like Git asking interaction.

**Note:** The `github` service is automatically configured, don't
call `autoConfigure()` as this will break the application.

Call `createForHost()` instead if you must access another GitHub account.


The following services can be safely used, and are covered by the BC
policy:

| Service-id          | Class                                          | Description                                                                                                      | 
|---------------------|------------------------------------------------|------------------------------------------------------------------------------------------------------------------|
| config              | `HubKit\Config`                                | Configuration                                                                                                    |
| guzzle              | `GuzzleHttp\Client`                            | Guzzle Http client                                                                                               |
| style               | `Symfony\Component\Console\Style\SymfonyStyle` | Symfony Style for mes sages, questions<br/>etc.                                                                  |
| process             | `HubKit\Service\CliProcess`                    | Run a shell command                                                                                              | 
| git                 | `HubKit\Service\Git`                           | Git base service                                                                                                 |
| git.branch          | `HubKit\Service\Git\GitBranch`                 | Git branch related operations                                                                                    |
| git.config          | `HubKit\Service\Git\GitConfig`                 |                                                                                                                  |
| git.temp_repository | `HubKit\Service\Git\GitTempRepository`         | Create a temporary Git repository for working, used by the git.file_reader                                       | 
| git.file_reader     | `HubKit\Service\Git\GitFileReader`             | File reader Git, allows to get a file without the need for a local checkout <br/>(using a temporary repository   |
| branch_splitsh_git  | `HubKit\Service\BranchSplitsh`                 | Branch splitting service, according to configuration.<br/>Requires a clean working dir                           |
| filesystem          | `HubKit\Service\Filesystem`                    | Filesystem service for writting/reading local file                                                               | 
| editor              | `HubKit\Service\Editor`                        | Allows to open the default editor for either changelog or file editing<br/>Keeps the process waiting till closed |
| github              | `HubKit\Service\GitHub`                        | GitHub adapter                                                                                                   |

See the source code for method usage and instructions. 
