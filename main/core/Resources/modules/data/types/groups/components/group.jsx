import React from 'react'
import {PropTypes as T, implementPropTypes} from '#/main/core/scaffolding/prop-types'

import {FormGroupWithField as FormGroupWithFieldTypes} from '#/main/core/layout/form/prop-types'
import {FormGroup} from '#/main/core/layout/form/components/group/form-group'
import {Group as GroupType} from '#/main/core/user/prop-types'
import {GroupsInput} from '#/main/core/data/types/groups/components/input'

const GroupsGroup = props =>
  <FormGroup {...props}>
    <GroupsInput {...props} />
  </FormGroup>

implementPropTypes(GroupsGroup, FormGroupWithFieldTypes, {
  value: T.arrayOf(T.shape(GroupType.propTypes))
})

export {
  GroupsGroup
}
