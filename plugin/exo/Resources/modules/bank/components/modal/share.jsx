import React, {Component} from 'react'
import {PropTypes as T} from 'prop-types'
import omit from 'lodash/omit'

import {tex} from '#/main/core/translation'
import {Modal} from '#/main/app/overlay/modal/components/modal'
import {registry} from '#/main/app/modals/registry'

import {update} from './../../../utils/utils'
import {FormGroup} from '#/main/core/layout/form/components/group/form-group'
import {UserTypeahead} from './../../../users/components/typeahead'

export const MODAL_SHARE = 'MODAL_SHARE'

// TODO : use core UserTypeahead

const SelectedUsers = props =>
  <ul className="list-group">
    {props.users.map((user) =>
      <li key={`selected-${user.id}`} className="list-group-item">
        {user.name}
        <button
          type="button"
          className="btn btn-link btn-sm"
          onClick={() => props.deselect(user)}
        >
          <span className="fa fa-fw fa-times" />
        </button>
      </li>
    )}
  </ul>

SelectedUsers.propTypes = {
  users: T.arrayOf(T.shape({
    id: T.string.isRequired,
    name: T.string.isRequired
  })).isRequired,
  deselect: T.func.isRequired
}

class ShareModal extends Component {
  constructor(props) {
    super(props)

    this.state = {
      adminRights: false,
      users: []
    }
  }

  selectUser(user) {
    this.setState(update(this.state, {
      users: {$push: [user]}
    }))
  }

  deselectUser(user) {
    this.setState(update(this.state, {
      users: {$splice: [[this.state.users.indexOf(user), 1]]}
    }))
  }

  render() {
    return (
      <Modal
        {...omit(this.props, 'handleShare')}
        className="share-modal"
      >
        <div className="modal-body">
          <div className="checkbox">
            <label htmlFor="share-admin-rights">
              <input
                id="share-admin-rights"
                type="checkbox"
                name="share-admin-rights"
                checked={this.state.adminRights}
                onChange={() => this.setState({
                  adminRights: !this.state.adminRights
                })}
              />
              {tex('share_admin_rights')}
            </label>
          </div>

          <FormGroup
            id="share-users"
            label={tex('share_with')}
          >
            <UserTypeahead
              handleSelect={this.selectUser.bind(this)}
            />
          </FormGroup>

          {0 < this.state.users.length &&
            <SelectedUsers
              users={this.state.users}
              deselect={this.deselectUser.bind(this)}
            />
          }
        </div>

        <button
          className="modal-btn btn btn-primary"
          disabled={0 === this.state.users.length}
          onClick={() => this.props.handleShare(this.state.users, this.state.adminRights)}
        >
          {tex('share')}
        </button>
      </Modal>
    )
  }
}

ShareModal.propTypes = {
  handleShare: T.func.isRequired
}

registry.add(MODAL_SHARE, ShareModal)

export {
  ShareModal
}
