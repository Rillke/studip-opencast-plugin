<?

use Opencast\LTI\OpencastLTI;
use Opencast\LTI\LtiLink;

?>

<? $studygroup_active = true; ?>
<? if ($flash['delete']) : ?>
    <?= createQuestion2(sprintf(    // question
        $_('Wollen Sie die Verknüpfung zur Series "%s" wirklich aufheben?'),
        $this->connectedSeries[0]['title']
    ),
        [   // approveParams
            'course_id' => $course_id,
            'series_id' => $this->connectedSeries[0]['series_id'],
            'delete'    => true
        ],
        [   // disapproveParams
            'cancel' => true
        ],
        $controller->url_for('course/remove_series/' . get_ticket())  // baseUrl
    ) ?>
<? endif ?>


<?= $this->render_partial('messages') ?>
<script>
    jQuery(function () {
        STUDIP.hasperm = <?= var_export(OCPerm::editAllowed($course_id)) ?>;
        OC.states = <?= json_encode($states) ?>;
        OC.initIndexpage();
    });
</script>

<?
if ($this->connectedSeries[0]['series_id']) :
    $current_user_id = $GLOBALS['auth']->auth['uid'];

    $lti_link = new LtiLink(
        OpencastLTI::getSearchUrl($this->course_id),
        $config['lti_consumerkey'],
        $config['lti_consumersecret']
    );

    $lti_link->addCustomParameter('tool', '/ltitools');

    if (OCPerm::editAllowed($course_id, $current_user_id)
        && (
            ($controller->isStudyGroup()
                && $controller->isStudentUploadForStudyGroupActivated()
            )
            || !$controller->isStudyGroup()
        )
    ) {
        $role = 'Instructor';
    } else if ($GLOBALS['perm']->have_studip_perm('autor', $course_id, $current_user_id)) {
        $role = 'Learner';
    }

    $lti_link->setUser($current_user_id, $role, True);
    $lti_link->setCourse($course_id);
    $lti_link->setResource(
        $this->connectedSeries[0]['series_id'],
        'series',
        'view complete series for course'
    );

    $launch_data = $lti_link->getBasicLaunchData();
    $signature   = $lti_link->getLaunchSignature($launch_data);

    $launch_data['oauth_signature'] = $signature;

    if (OCPerm::editAllowed($course_id)
        && \Config::get()->OPENCAST_ALLOW_STUDIO
        && $config['service_url'] . '/lti' != OpencastLTI::getSearchUrl($this->course_id)
    ) {
        $studio_lti_link = new LtiLink(
            $config['service_url'] . '/lti',
            $config['lti_consumerkey'],
            $config['lti_consumersecret']
        );

        $studio_lti_link->addCustomParameter('tool', '/ltitools');

        if (OCPerm::editAllowed($course_id, $current_user_id)
            && (($controller->isStudyGroup() && $controller->isStudentUploadForStudyGroupActivated() || !$controller->isStudyGroup()))) {
            $role = 'Instructor';
        } else if ($GLOBALS['perm']->have_studip_perm('autor', $course_id, $current_user_id)) {
            $role = 'Learner';
        }

        $studio_lti_link->setUser($current_user_id, $role, True);
        $studio_lti_link->setCourse($course_id);
        $studio_lti_link->setResource(
            $this->connectedSeries[0]['series_id'],
            'series'
        );

        $studio_launch_data = $studio_lti_link->getBasicLaunchData();
        $studio_signature   = $studio_lti_link->getLaunchSignature($studio_launch_data);

        $studio_launch_data['oauth_signature'] = $studio_signature;
    }
    ?>

    <script>
        OC.ltiCall('<?= $lti_link->getLaunchURL() ?>', <?= json_encode($launch_data) ?>, function () {
            jQuery('img.previewimage').each(function () {
                this.src = this.dataset.src;
            });

            <? if ($studio_lti_link && \Config::get()->OPENCAST_ALLOW_STUDIO): ?>
            OC.lti_done = 0;
            OC.ltiCall('<?= $studio_lti_link->getLaunchURL() ?>', <?= json_encode($studio_launch_data) ?>, function () {
            });
            <? endif ?>
        });
    </script>

<? foreach ($GLOBALS['SEM_CLASS'] as $sem_class) : ?>
    <? if ($sem_class['name'] == 'Studiengruppen') : ?>
        <? if (!$sem_class['modules']['OpenCast']['activated'] && $sem_class['modules']['OpenCast']['sticky']) : ?>
            <?= $studygroup_active = false;
                break; ?>
        <? endif ?>
    <? endif ?>
<? endforeach ?>

<?
endif;

$sidebar = Sidebar::get();
if (OCPerm::editAllowed($course_id)) {
    $actions = new ActionsWidget ();
    $upload  = '';

    if (!empty($connectedSeries)) {
        if (!$controller->isStudyGroup()) {
            $actions->addLink(
                $_('Verknüpfung aufheben'),
                $controller->url_for('course/remove_series/' . get_ticket()),
                Icon::create('trash')
            );
        }

        if ($can_schedule) {
            $isStudentUploadForStudyGroupActivated = $controller->isStudentUploadForStudyGroupActivated();
            if (($controller->isStudyGroup() && $isStudentUploadForStudyGroupActivated)
                || !$controller->isStudyGroup()
                || ($controller->isStudyGroup() && !$isStudentUploadForStudyGroupActivated && Config::get()->OPENCAST_ALLOW_STUDYGROUP_CONF)) {
                $actions->addLink(
                    $_('Medien hochladen'),
                    $controller->url_for('course/upload'),
                    Icon::create('upload'),
                    []
                );

                if (\Config::get()->OPENCAST_ALLOW_STUDIO) {
                    $actions->addLink(
                        $_('Video aufnehmen'),
                        URLHelper::getLink(
                            $config['service_url'] . '/studio/index.html',
                            [
                                'cid'             => null,
                                'upload.seriesId' => $connectedSeries[0]['series_id'],
                                'upload.acl'      => 'false',
                                'return.target'   => $controller->url_for('course/index', ['cid' => $course_id]),
                                'return.label'    => 'Zurückkehren zu Stud.IP'
                            ]
                        ),
                        Icon::create('video2'),
                        ['target' => '_blank']
                    );
                }
            }

            // TODO: Schnittool einbinden - Passender Workflow kucken

            if ($GLOBALS['perm']->have_perm('root')) {
                $actions->addLink(
                    $_('Kursspezifischen Workflow konfigurieren'),
                    $controller->url_for('course/workflow'),
                    Icon::create('admin'),
                    ['data-dialog' => 'size=auto']
                );
            }
        }

        if (!$controller->isStudyGroup()) {
            if ($coursevis == 'visible') {
                $actions->addLink(
                    $_('Reiter verbergen'),
                    $controller->url_for('course/toggle_tab_visibility/' . get_ticket()),
                    Icon::create('visibility-visible')
                );
            } else {
                $actions->addLink(
                    $_('Reiter sichtbar machen'),
                    $controller->url_for('course/toggle_tab_visibility/' . get_ticket()),
                    Icon::create('visibility-invisible')
                );
            }
        }

        if (Config::get()->OPENCAST_SHOW_TOS && !$GLOBALS['perm']->have_perm('root')) {
            $actions->addLink(
                $_('Nutzungsvereinbarung ablehnen'),
                $controller->url_for('course/withdraw_tos/' . get_ticket()),
                Icon::create('decline')
            );
        }

        if ($GLOBALS['perm']->have_perm('root')) {
            if ($can_schedule) {
                $actions->addLink(
                    $_('Medienaufzeichnung verbieten'),
                    $controller->url_for('course/toggle_schedule/' . get_ticket()),
                    Icon::create('video+accept'),
                    [
                        'title' => $_('Die Medienaufzeichnung ist momentan erlaubt.')
                    ]
                );
            } else {
                $actions->addLink(
                    $_('Medienaufzeichnung erlauben'),
                    $controller->url_for('course/toggle_schedule/' . get_ticket()),
                    Icon::create('video+decline'),
                    [
                        'title' => $_('Die Medienaufzeichnung ist momentan verboten.')
                    ]
                );
            }
        }

        if (!$controller->isStudyGroup() || Config::get()->OPENCAST_ALLOW_STUDYGROUP_CONF ) {
            if ($controller->isDownloadAllowed()) {
                $actions->addLink(
                    $_('Downloads verbieten'),
                    $controller->url_for('course/disallow_download/' . get_ticket()),
                    Icon::create('download+accept'),
                    [
                        'title' => $_('Downloads sind momentan erlaubt.')
                    ]
                );
            } else {
                $actions->addLink(
                    $_('Downloads erlauben'),
                    $controller->url_for('course/allow_download/' . get_ticket()),
                    Icon::create('download+decline'),
                    [
                        'title' => $_('Downloads sind momentan verboten.')
                    ]
                );
            }

            if (!$controller->isStudygroup() || ($controller->isStudyGroup() && $isStudentUploadForStudyGroupActivated && !CourseConfig::get($course_id)->OPENCAST_MEDIAUPLOAD_LINKED_COURSE)) {
                if ($controller->isStudentUploadEnabled()) {
                    $actions->addLink(
                        $_('Hochladen durch Studierende verbieten'),
                        $controller->url_for('course/disallow_students_upload/' . get_ticket()),
                        Icon::create('upload+accept'),
                        [
                            'title' => $_('Das Hochladen durch Studierende ist momentan erlaubt.')
                        ]
                    );
                } elseif ($studygroup_active) {
                    $actions->addLink(
                        $_('Hochladen durch Studierende erlauben'),
                        $controller->url_for('course/allow_students_upload/' . get_ticket()),
                        Icon::create('upload'),
                        [
                            'title' => $_('Das Hochladen durch Studierende ist momentan verboten.')
                        ]
                    );
                }
            }

            if (!$controller->isStudyGroup() || Config::get()->OPENCAST_ALLOW_STUDYGROUP_CONF ) {
                $vis = !is_null(CourseConfig::get($this->course_id)->COURSE_HIDE_EPISODES)
                    ? boolval(CourseConfig::get($this->course_id)->COURSE_HIDE_EPISODES)
                    : \Config::get()->OPENCAST_HIDE_EPISODES;
                if ($vis) {
                    $actions->addLink(
                        $_('Neue Videos für alle Teilnehmenden sichtbar schalten'),
                        $controller->url_for('course/course_visibility/' . get_ticket() . '/' . intval(!$vis)),
                        Icon::create('visibility-invisible'),
                        [
                            'title' => $_('Neue Medien sind momentan standardmäßig nur für Lehrende sichtbar.')
                        ]
                    );
                } else {
                    $actions->addLink(
                        $_('Neue Videos nur für Lehrende sichtbar schalten'),
                        $controller->url_for('course/course_visibility/' . get_ticket() . '/' . intval(!$vis)),
                        Icon::create('visibility-visible'),
                        [
                            'title' => $_('Neue Medien sind momentan standardmäßig für alle Teilnehmenden der Veranstaltung sichtbar.')
                        ]
                    );
                }
            }
        }

    } else {
        if (!$controller->isStudyGroup() || Config::get()->OPENCAST_ALLOW_STUDYGROUP_CONF) {
            $actions->addLink(
                $_('Neue Series anlegen'),
                $controller->url_for('course/create_series'),
                Icon::create('tools')
            );

            if ($GLOBALS['perm']->have_perm('root')) {
                $actions->addLink(
                    $_('Vorhandene Series verknüpfen'),
                    $controller->url_for('course/config'),
                    Icon::create('group'),
                    [
                        'data-dialog' => 'width=550;height=500'
                    ]
                );
            }
        }
    }

    $sidebar->addWidget($actions);
    Helpbar::get()->addPlainText(
        '',
        $_('Hier sehen Sie eine Übersicht ihrer Vorlesungsaufzeichnungen. Sie können über den Unterpunkt Aktionen weitere Medien zur Liste der Aufzeichnungen hinzufügen. Je nach Größe der Datei kann es einige Zeit in Anspruch nehmen, bis die entsprechende Aufzeichnung in der Liste sichtbar ist. Weiterhin ist es möglich die ausgewählten Sichtbarkeit einer Aufzeichnung innerhalb der Veranstaltung direkt zu ändern.')
    );
} else {
    Helpbar::get()->addPlainText('', $_('Hier sehen Sie eine Übersicht ihrer Vorlesungsaufzeichnungen.'));
}

Helpbar::get()->addLink('Bei Problemen: ' . Config::get()->OPENCAST_SUPPORT_EMAIL, 'mailto:' . Config::get()->OPENCAST_SUPPORT_EMAIL . '?subject=[Opencast] Feedback');
?>

<? if (OCPerm::editAllowed($course_id) && !$studygroup_active) : ?>
    <?= MessageBox::error($_('Das Opencast Plugin ist momentan nicht für Studiengruppen aktiv. Wenden Sie sich an einen Admin, um das Problem zu beheben.')); ?>
<? endif ?>

<? if (!(empty($ordered_episode_ids)) || !(empty($wip_episodes))) : ?>
    <? if (OCPerm::editAllowed($course_id)) : ?>
        <?= $this->render_partial('course/_wip_episode') ?>
    <? endif ?>

    <? if (!(empty($ordered_episode_ids))) : ?>
        <?= $this->render_partial('course/_episode') ?>
    <? endif ?>
<? else: ?>
    <? if (empty($this->connectedSeries) && OCPerm::editAllowed($course_id)) : ?>
        <? if ($this->config_error) : ?>
            <?= MessageBox::error($_('Für aktuell verknüpfte Serie ist eine fehlerhafte Konfiguration hinterlegt!')) ?>
        <? else : ?>
            <? if ($GLOBALS['perm']->have_perm('root')) : ?>
                <?= MessageBox::info($_('Sie haben noch keine Series aus Opencast mit dieser Veranstaltung verknüpft. Bitte erstellen Sie eine neue Series oder verknüpfen eine bereits vorhandene Series.')) ?>
            <? else : ?>
                <?= MessageBox::info($_('Sie haben noch keine Series aus Opencast mit dieser Veranstaltung verknüpft. Bitte erstellen Sie eine neue Series.')) ?>
            <? endif ?>
        <? endif; ?>
    <? else: ?>
        <?= MessageBox::info($_('Es wurden bislang keine Vorlesungsaufzeichnungen bereitgestellt.')); ?>
    <? endif; ?>
<? endif; ?>

<!--- hidden -->
<div class="hidden" id="course_id" data-courseId="<?= $course_id ?>"></div>
<div id="visibility_dialog" style="display: none">
    <form class="default" method="post">
        <fieldset>
            <legend><?= $_('Sichtbarkeit einstellen') ?></legend>

            <label>
                <input type="radio" name="visibility" value="invisible">
                <span>
                    <?= $_('Unsichtbar - Für Lehrende und Tutor/-innen dieser Veranstaltung sichtbar') ?>
                </span>
            </label>

            <label>
                <input type="radio" name="visibility" value="visible">
                <span>
                    <?= $_('Sichtbar - Für Teilnehmende dieser Veranstaltung sichtbar') ?>
                </span>
            </label>

            <? if ($multiconnected) : ?>
                <label class="oc_muted">
                    <input type="radio" name="visibility" value="free" disabled="disabled" style="float: left">
                    <span>
                        <?= $_('Diese Videoserie ist mit mehreren Seminaren verknüpft, das Video kann daher nicht freigegeben werden.') ?>
                    </span>
                </label>
            <? else : ?>
                <label>
                    <input type="radio" name="visibility" value="free">
                    <span>
                        <?= $_('Freigeben - Dieses Video ist für jeden sichtbar') ?>
                    </span>
                </label>
            <? endif ?>
        </fieldset>

        <footer data-dialog-button>
            <?= Studip\Button::createAccept(
                _('Speichern'),
                [
                    'onclick' => "OC.setVisibility(jQuery('#visibility_dialog input[name=visibility]:checked').val(), jQuery('#visibility_dialog').attr('data-episode_id'));return false;"
                ]
            ) ?>
            <?= Studip\Button::createCancel(
                _('Abbrechen'),
                ['onclick' => "jQuery('#visibility_dialog').dialog('close');return false;"]
            ) ?>
        </footer>
    </form>
</div>
