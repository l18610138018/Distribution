import React from 'react'
import {PropTypes as T} from 'prop-types'
import classes from 'classnames'
import omit from 'lodash/omit'

import {trans} from '#/main/core/translation'
import {toKey} from '#/main/core/scaffolding/text/utils'
import {CallbackButton} from '#/main/app/buttons/callback/components/button'
import {Modal} from '#/main/app/overlay/modal/components/modal'

import {Walkthrough as WalkthroughTypes} from '#/main/app/overlay/walkthrough/prop-types'

const WalkthroughsModal = props =>
  <Modal
    {...omit(props, 'walkthroughs', 'start')}
    icon="fa fa-fw fa-street-view"
    title={trans('walkthroughs')}
  >
    <div className="list-group walkthroughs-list">
      {props.walkthroughs.map(walkthrough =>
        <CallbackButton
          key={toKey(walkthrough.title)}
          className="list-group-item"
          callback={() => {
            props.fadeModal()
            props.start(walkthrough.scenario, walkthrough.additional, walkthrough.documentation)
          }}
        >
          {walkthrough.difficulty &&
            <span className={classes('label', {
              'label-success': 'easy'         === walkthrough.difficulty,
              'label-warning': 'intermediate' === walkthrough.difficulty,
              'label-danger' : 'hard'         === walkthrough.difficulty,
              'label-black'  : 'expert'       === walkthrough.difficulty
            })}>
              {trans(walkthrough.difficulty)}
            </span>
          }

          {walkthrough.title}
          {walkthrough.description &&
            <small>{walkthrough.description}</small>
          }
        </CallbackButton>
      )}
    </div>
  </Modal>

WalkthroughsModal.propTypes = {
  walkthroughs: T.arrayOf(T.shape(
    WalkthroughTypes.propTypes
  )),
  start: T.func.isRequired,
  fadeModal: T.func.isRequired
}

WalkthroughsModal.defaultProps = {
  walkthroughs: []
}

export {
  WalkthroughsModal
}
